<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QuestionDuplicateService
{
    public static function canAccess(?User $user): bool
    {
        return $user !== null && $user->can('view question quality');
    }

    public function forgetCache(): void
    {
        Cache::forget('admin:question_duplicates:v1');
    }

    /** @param list<int> $questionIds */
    public static function groupKey(array $questionIds): string
    {
        $ids = array_values(array_unique(array_map('intval', $questionIds)));
        sort($ids);

        return implode(',', $ids);
    }

    /** @return array<string, true> */
    private function dismissedGroupKeys(): array
    {
        if (! Schema::hasTable('question_duplicate_dismissals')) {
            return [];
        }

        return DB::table('question_duplicate_dismissals')
            ->pluck('group_key')
            ->mapWithKeys(fn ($k) => [(string) $k => true])
            ->all();
    }

    /** @param list<int> $questionIds */
    public function dismissGroup(array $questionIds, string $type, ?int $adminId): void
    {
        $ids = array_values(array_unique(array_map('intval', $questionIds)));
        sort($ids);
        if (count($ids) < 2) {
            return;
        }

        $key = self::groupKey($ids);
        $now = now();
        $row = [
            'question_ids' => json_encode($ids),
            'type' => in_array($type, ['exact', 'near'], true) ? $type : 'near',
            'admin_id' => $adminId,
            'updated_at' => $now,
        ];
        if (DB::table('question_duplicate_dismissals')->where('group_key', $key)->exists()) {
            DB::table('question_duplicate_dismissals')->where('group_key', $key)->update($row);
        } else {
            DB::table('question_duplicate_dismissals')->insert(array_merge($row, [
                'group_key' => $key,
                'created_at' => $now,
            ]));
        }
    }

    /**
     * @param list<array> $groups
     * @return list<array>
     */
    private function filterDismissedGroups(array $groups, array $dismissed): array
    {
        return array_values(array_filter($groups, function ($g) use ($dismissed) {
            $ids = array_map(fn ($q) => (int) ($q['id'] ?? 0), $g['questions'] ?? []);
            $key = self::groupKey($ids);

            return ! isset($dismissed[$key]);
        }));
    }

    /** Cache yoksa tarama yapmaz. */
    public function cachedStats(): ?array
    {
        $data = Cache::get('admin:question_duplicates:v1');
        if (! is_array($data) || ! isset($data['stats']) || ! is_array($data['stats'])) {
            return null;
        }

        return $data['stats'];
    }

    /**
     * Birebir kopyaları soft-delete eder; grupta en eski kalır. Jeton/cevap geçmişi korunur.
     *
     * @return array{deleted:int,repointed_duels:int,kept:int,skipped:int}
     */
    public function purgeExactDuplicates(): array
    {
        $data = $this->find(true);
        $toDelete = [];
        $keepMap = [];

        foreach ($data['groups'] as $g) {
            if (($g['type'] ?? '') !== 'exact') {
                continue;
            }
            $qs = $g['questions'] ?? [];
            usort($qs, fn ($a, $b) => ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0)));
            if ($qs === []) {
                continue;
            }
            $keepId = (int) $qs[0]['id'];
            for ($i = 1, $n = count($qs); $i < $n; $i++) {
                $id = (int) $qs[$i]['id'];
                if ($id <= 0 || $id === $keepId) {
                    continue;
                }
                $toDelete[] = $id;
                $keepMap[$id] = $keepId;
            }
        }

        $toDelete = array_values(array_unique($toDelete));
        if ($toDelete === []) {
            return ['deleted' => 0, 'repointed_duels' => 0, 'kept' => 0, 'skipped' => 0];
        }

        $repointed = 0;
        $deleted = 0;
        $skipped = 0;

        DB::transaction(function () use ($toDelete, $keepMap, &$repointed, &$deleted, &$skipped) {
            foreach ($keepMap as $fromId => $keepId) {
                $repointed += DB::table('duels')
                    ->where('current_question_id', $fromId)
                    ->update(['current_question_id' => $keepId]);
            }

            foreach (array_chunk($toDelete, 100) as $chunk) {
                $questions = Question::query()->whereIn('id', $chunk)->lockForUpdate()->get();
                foreach ($questions as $question) {
                    if ($question->trashed()) {
                        $skipped++;

                        continue;
                    }
                    $question->delete();
                    $deleted++;
                }
            }
        });

        $this->forgetCache();

        return [
            'deleted' => $deleted,
            'repointed_duels' => $repointed,
            'kept' => count(array_unique(array_values($keepMap))),
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array{stats: array, groups: list<array>}
     */
    public function find(bool $fresh = false): array
    {
        $key = 'admin:question_duplicates:v1';
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, now()->addMinutes(15), fn () => $this->scan());
    }

    /**
     * @return array{stats: array, groups: list<array>}
     */
    private function scan(): array
    {
        $exact = [];
        $byPrefix = [];
        $bySortedHead = [];
        $items = [];
        $i = 0;

        DB::table('questions')
            ->select(['id', 'category_id', 'question_level', 'is_active', 'question'])
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$exact, &$byPrefix, &$bySortedHead, &$items, &$i) {
                foreach ($rows as $row) {
                    $tr = $this->extractTr((string) $row->question);
                    $norm = $this->normalize($tr);
                    $w = $this->words($norm);
                    $wc = count($w);
                    $set = array_values(array_unique($w));
                    $items[$i] = [
                        'id' => (int) $row->id,
                        'category_id' => (int) $row->category_id,
                        'level' => (string) $row->question_level,
                        'active' => (int) $row->is_active === 1,
                        'norm' => $norm,
                        'wc' => $wc,
                        'set' => $set,
                        'text' => $tr,
                    ];
                    $exact[$norm][] = $i;

                    if ($wc >= 5) {
                        $byPrefix[implode(' ', array_slice($w, 0, 5))][] = $i;
                    }
                    $uniq = $set;
                    sort($uniq, SORT_STRING);
                    if (count($uniq) >= 6) {
                        $bySortedHead[implode(' ', array_slice($uniq, 0, 6))][] = $i;
                    }
                    $i++;
                }
            });

        $exactGroups = [];
        $exactQuestionIds = [];
        foreach ($exact as $norm => $idxs) {
            if ($norm === '' || count($idxs) < 2) {
                continue;
            }
            $members = array_map(fn ($k) => $items[$k], $idxs);
            foreach ($members as $m) {
                $exactQuestionIds[$m['id']] = true;
            }
            $exactGroups[] = [
                'type' => 'exact',
                'size' => count($members),
                'questions' => $members,
            ];
        }

        $seenPairs = [];
        $nearPairs = [];
        $collect = function (array $buckets) use (&$items, &$seenPairs, &$nearPairs) {
            foreach ($buckets as $idxs) {
                $n = count($idxs);
                if ($n < 2 || $n > 80) {
                    continue;
                }
                for ($a = 0; $a < $n; $a++) {
                    for ($b = $a + 1; $b < $n; $b++) {
                        $i1 = $idxs[$a];
                        $i2 = $idxs[$b];
                        if ($i1 > $i2) {
                            [$i1, $i2] = [$i2, $i1];
                        }
                        $key = $i1.'-'.$i2;
                        if (isset($seenPairs[$key])) {
                            continue;
                        }
                        $seenPairs[$key] = true;
                        $x = $items[$i1];
                        $y = $items[$i2];
                        if ($x['norm'] === $y['norm']) {
                            continue;
                        }
                        if ($x['wc'] < 6 || $y['wc'] < 6) {
                            continue;
                        }
                        $ov = $this->overlapMin($x['set'], $y['set']);
                        $jac = $this->jaccard($x['set'], $y['set']);
                        if ($ov >= 0.90 || $jac >= 0.85) {
                            $nearPairs[] = [$i1, $i2];
                        }
                    }
                }
            }
        };
        $collect($byPrefix);
        $collect($bySortedHead);

        $parent = [];
        $find = function ($x) use (&$parent, &$find) {
            if (! isset($parent[$x])) {
                $parent[$x] = $x;
            }
            if ($parent[$x] !== $x) {
                $parent[$x] = $find($parent[$x]);
            }

            return $parent[$x];
        };
        foreach ($nearPairs as [$a, $b]) {
            $ra = $find($a);
            $rb = $find($b);
            if ($ra !== $rb) {
                $parent[$ra] = $rb;
            }
        }

        $clusters = [];
        foreach ($nearPairs as [$a, $b]) {
            $r = $find($a);
            $clusters[$r][$a] = true;
            $clusters[$r][$b] = true;
        }

        $nearGroups = [];
        $nearQuestionIds = [];
        foreach ($clusters as $c) {
            $members = [];
            foreach (array_keys($c) as $k) {
                $members[] = $items[$k];
                $nearQuestionIds[$items[$k]['id']] = true;
            }
            usort($members, fn ($x, $y) => $x['id'] <=> $y['id']);
            $nearGroups[] = [
                'type' => 'near',
                'size' => count($members),
                'questions' => $members,
            ];
        }

        $catIds = [];
        foreach (array_merge($exactGroups, $nearGroups) as $g) {
            foreach ($g['questions'] as $q) {
                $catIds[$q['category_id']] = true;
            }
        }
        $catNames = Category::query()
            ->whereIn('id', array_keys($catIds))
            ->get()
            ->mapWithKeys(function ($c) {
                $name = method_exists($c, 'getTranslation')
                    ? (string) $c->getTranslation('name', 'tr')
                    : (string) ($c->name ?? '');

                return [$c->id => $name !== '' ? $name : ('#'.$c->id)];
            })
            ->all();

        $attachCat = function (array $groups) use ($catNames) {
            foreach ($groups as &$g) {
                foreach ($g['questions'] as &$q) {
                    $q['category'] = $catNames[$q['category_id']] ?? ('#'.$q['category_id']);
                    unset($q['norm'], $q['set']);
                }
                unset($q);
            }
            unset($g);

            return $groups;
        };

        $exactGroups = $attachCat($exactGroups);
        $nearGroups = $attachCat($nearGroups);

        $dismissed = $this->dismissedGroupKeys();
        $exactGroups = $this->filterDismissedGroups($exactGroups, $dismissed);
        $nearGroups = $this->filterDismissedGroups($nearGroups, $dismissed);

        $exactQuestionIds = [];
        foreach ($exactGroups as $g) {
            foreach ($g['questions'] as $q) {
                $exactQuestionIds[$q['id']] = true;
            }
        }
        $nearQuestionIds = [];
        foreach ($nearGroups as $g) {
            foreach ($g['questions'] as $q) {
                $nearQuestionIds[$q['id']] = true;
            }
        }

        usort($exactGroups, fn ($a, $b) => $b['size'] <=> $a['size'] ?: $a['questions'][0]['id'] <=> $b['questions'][0]['id']);
        usort($nearGroups, fn ($a, $b) => $b['size'] <=> $a['size'] ?: $a['questions'][0]['id'] <=> $b['questions'][0]['id']);

        $involved = $exactQuestionIds + $nearQuestionIds;

        return [
            'stats' => [
                'total_questions' => count($items),
                'exact_groups' => count($exactGroups),
                'exact_questions' => count($exactQuestionIds),
                'near_groups' => count($nearGroups),
                'near_questions' => count($nearQuestionIds),
                'involved' => count($involved),
            ],
            'groups' => array_merge($exactGroups, $nearGroups),
        ];
    }

    private function extractTr(string $raw): string
    {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return trim((string) ($decoded['tr'] ?? $decoded['en'] ?? ''));
        }

        return trim($raw);
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = strtr($s, [
            'ı' => 'i', 'İ' => 'i', 'I' => 'i', 'ğ' => 'g', 'ü' => 'u',
            'ş' => 's', 'ö' => 'o', 'ç' => 'c', 'â' => 'a', 'î' => 'i', 'û' => 'u',
        ]);
        $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s);
    }

    /** @return list<string> */
    private function words(string $norm): array
    {
        if ($norm === '') {
            return [];
        }

        return preg_split('/\s+/u', $norm, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private function jaccard(array $a, array $b): float
    {
        $sa = array_fill_keys($a, true);
        $sb = array_fill_keys($b, true);
        $inter = 0;
        foreach ($sa as $w => $_) {
            if (isset($sb[$w])) {
                $inter++;
            }
        }
        $union = count($sa) + count($sb) - $inter;

        return $union > 0 ? $inter / $union : 0.0;
    }

    private function overlapMin(array $a, array $b): float
    {
        $sa = array_fill_keys($a, true);
        $sb = array_fill_keys($b, true);
        $inter = 0;
        foreach ($sa as $w => $_) {
            if (isset($sb[$w])) {
                $inter++;
            }
        }
        $mn = min(count($sa), count($sb));

        return $mn > 0 ? $inter / $mn : 0.0;
    }
}
