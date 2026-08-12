<?php

namespace App\Console\Commands;

use App\Services\QuestionDuplicateService;
use Illuminate\Console\Command;

class PurgeExactDuplicateQuestionsCommand extends Command
{
    protected $signature = 'questions:purge-exact-duplicates {--dry-run : Sadece say, silme}';

    protected $description = 'Birebir soru kopyalarını soft-delete eder (en eski kalır).';

    public function handle(QuestionDuplicateService $finder): int
    {
        if ($this->option('dry-run')) {
            $data = $finder->find(true);
            $extras = 0;
            foreach ($data['groups'] as $g) {
                if (($g['type'] ?? '') !== 'exact') {
                    continue;
                }
                $extras += max(0, ((int) ($g['size'] ?? 0)) - 1);
            }
            $this->info("Silinecek birebir kopya: {$extras}");

            return self::SUCCESS;
        }

        $result = $finder->purgeExactDuplicates();
        $this->info('Soft-delete: '.$result['deleted'].' · tutulan grup: '.$result['kept']
            .' · düello yönlendirme: '.$result['repointed_duels']
            .' · atlanan: '.$result['skipped']);

        return self::SUCCESS;
    }
}
