<?php

namespace App\Http\Repositories;

use App\Http\Custom\Response;
use Illuminate\Database\Eloquent\Model;
use App\Http\Repositories\RepositoryInterface;
use App\Models\Message;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BaseRepository implements RepositoryInterface
{
    public $codePrefix;
    protected $id = null;
    protected $item = null;
    protected $model = null;
    protected $withCount = [];
    protected $with = [];
    protected $where = [];
    protected $nestedModel = null;
    protected $nestedId = null;
    protected $nestedColumnName = null;

    /**
     * __construct function
     *
     * @param Model $model
     */
    public function __construct(Model $model, $codePrefix = null)
    {
        $this->model = $model;
        $this->codePrefix = $codePrefix;
    }

    public function setId($id, $itemFind = true)
    {
        if ($id > 0) $this->id = $id;
        if ($itemFind) $this->item = $this->model->find($this->id, true);
        return $this;
    }

    public function withCount(array $relations = [])
    {
        if (!empty($relations)) {
            $this->withCount = $relations;
        }
        return $this;
    }

    public function with(array $relations = [])
    {
        if (!empty($relations)) {
            $this->with = $relations;
        }
        return $this;
    }

    public function setNested(Model $nestedModel, $nestedId, $nestedColumnName)
    {
        if (!empty($nestedId) && !empty($nestedColumnName)) {
            $this->nestedModel = $nestedModel;
            $this->nestedId = $nestedId;
            $this->nestedColumnName = $nestedColumnName;
        }
        return $this;
    }

    /**
     * Setter for model
     *
     * @param Model $model Model object
     */
    public function setModel(Model $model)
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Get by ID
     *
     * @param integer $id
     *
     * @return mixed
     */
    public function get($id)
    {
        return $this->model->find($id, true);
    }

    /**
     * Get all
     *
     * @return mixed
     */
    public function all()
    {
        return $this->model->all();
    }

    public function nextId()
    {
        $nextId = $this->model->max('id') + 1;
        return $nextId;
    }

    public function setWhere(array $where)
    {
        if (!empty($where))
            $this->where = $where;

        return $this;
    }

    public function filter($limit = null)
    {
        $query = $this->model->query();

        if (!empty($this->nestedId) && !empty($this->nestedColumnName))
            $query->where($this->nestedColumnName, $this->nestedId);

        if (!empty($this->withCount))
            $query->withCount($this->withCount);

        if (!empty($this->with))
            $query->with($this->with);

        // Account ID kontrolü kaldırıldı - User modeli ile çalışıyor

        #where
        if (!empty($this->where)) {
            $query->where($this->where);
        }

        $tab = request('tab');
        if (!empty($tab)) {
            if (empty($this->model->tabs) || !in_array($tab, array_keys($this->model->tabs))) {
                throw new HttpResponseException(
                    Response::error(['tab' => ["Doesn't support tab"]], null, JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
                );
            }

            $filters = $this->model->tabs[$tab];

            $query->where(function ($subquery) use ($filters) {
                foreach ($filters as $filter) {
                    list($column, $operator, $value) = $filter;
                    $subquery->where($column, $operator, $value);
                }
            });
        }

        #default sort
        $sort = request('sort');
        if (empty($sort)) {
            $query->orderBy('id', 'desc');
        }

        if (empty($limit)) $limit = Config::get('limit');
        return $query->filter()->sort()->paginate($limit);
    }


    /**
     * Create an object and save
     *
     * @param array $input Input data
     *
     * @return Model
     */
    public function create($input)
    {
        if (!empty($this->nestedId) && !empty($this->nestedColumnName)) {
            $nestedData = $this->nestedModel->find($this->nestedId);

            if (!$nestedData) {
                $errors = ValidationException::withMessages([$this->nestedColumnName => 'This ' . $this->nestedId . ' is not found'])->errors();
                throw new HttpResponseException(
                    Response::error($errors, null, JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
                );
            }

            $input[$this->nestedColumnName] = $this->nestedId;
        }

        if (isset($this->model->addUserId) && $this->model->addUserId)
            $input['user_id'] = Auth::user()->id;

        return $this->model->create($input);
    }

    public function upsert($input)
    {
        if ($this->id > 0) {
            $item = $this->update($this->id, $input);
        } else {
            $item = $this->create($input);
        }

        return $item;
    }

    public function find($id)
    {
        if (!empty($this->nestedId) && !empty($this->nestedColumnName)) {
            $nestedData = $this->nestedModel->find($this->nestedId);

            if (!$nestedData) {
                $errors = ValidationException::withMessages([$this->nestedColumnName => 'This ' . $this->nestedId . ' is not found'])->errors();
                throw new HttpResponseException(
                    Response::error($errors, null, JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
                );
            }

            $this->model->where($this->nestedColumnName, $this->nestedId);
        }

        $data = $this->model->find($id, true);

        return $data;
    }

    /**
     * Deletes a record.
     *
     * @param integer $id
     */
    public function delete($id)
    {
        if (!empty($this->nestedId) && !empty($this->nestedColumnName)) {
            $nestedData = $this->nestedModel->find($this->nestedId, true);

            if (!$nestedData) {
                $errors = ValidationException::withMessages([$this->nestedColumnName => 'This ' . $this->nestedId . ' is not found'])->errors();
                throw new HttpResponseException(
                    Response::error($errors, null, JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
                );
            }

            $this->model->where($this->nestedColumnName, $this->nestedId);
        }


        $object = $this->model->find($id);

        if (!$object) {
            $errors = ValidationException::withMessages(['id' => 'This id ' . $id . ' is not found'])->errors();
            throw new HttpResponseException(
                Response::error($errors, null, JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
            );
        }
        return $object->delete();
    }


    /**
     * Updates a post.
     *
     * @param integer $id
     * @param array   $data
     *
     * @return Model
     */
    public function update($id, array $data)
    {
        if (!empty($this->nestedId) && !empty($this->nestedColumnName)) {
            $nestedData = $this->nestedModel->find($this->nestedId, true);
            $this->model->where($this->nestedColumnName, $this->nestedId);
        }

        if (!empty($this->with))
            $this->model->load($this->with);

        $object = $this->model->find($id);

        if (!$object) {
            $errors = ValidationException::withMessages(['id' => 'This id ' . $id . ' is not found'])->errors();
            throw new HttpResponseException(
                Response::error($errors, null, JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
            );
        }

        $object->update($data);

        return $object;
    }

    /**
     * Get paganiated data with simple interface
     *
     * @param integer $itemPerPage The number of items per page
     *
     * @return mixed
     */
    public function getSimplePaginatedData($itemPerPage = 50)
    {
        return $this->model->orderBy('id', 'ASC')->simplePaginate($itemPerPage);
    }

    /**
     * Get paganiated data
     *
     * @param integer $itemPerPage The number of items per page
     *
     * @return mixed
     */
    public function getPaginatedData($itemPerPage = 50)
    {
        return $this->model->orderBy('id', 'ASC')->paginate($itemPerPage);
    }

    /**
     * Get data
     *
     * @param array $params The query parameter array with field names as keys
     *
     * @return mixed
     */
    public function getData($params = [], $with = [])
    {
        $model = $this->model->orderBy('id', 'ASC');

        foreach ($params as $field => $value) {
            $model->where($field, $value);
        }

        return $model->with($with)->get();
    }

    /**
     * Get latest
     *
     * @param array $params The query parameter array with field names as keys
     *
     * @return mixed
     */
    public function getLatest($params = [], $with = [])
    {
        $model = $this->model->orderBy('id', 'ASC');

        foreach ($params as $field => $value) {
            $model->where($field, $value);
        }

        return $model->with($with)->first();
    }

    /**
     * Rules configuration
     *
     * @param string $type   Type of the rules
     * @param Model  $object Object of the model
     *
     * @return array
     */
    public function rules($type = '', Model $object = null)
    {
        $rules = [];

        return $rules;
    }

    public function deleteByNestedId($id, $nestedId, $nestedTableName, $nestedColumnName)
    {
        $validator = Validator::make(['id' => $id, $nestedColumnName => $nestedId], [
            $nestedColumnName => [
                'required',
                Rule::exists($nestedTableName, 'id')->where(function (Builder $query) {
                    return $query->where('account_id', auth()->user()->account_id);
                }),
            ]
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->messages();
            throw new HttpResponseException(
                Response::error($errors, null, JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
            );
        }

        $item = $this->model->where($nestedColumnName, $nestedId)->find($id);

        if ($item) {
            return $item->delete();
        } else {
            throw new HttpResponseException(
                Response::error(['id' => [
                    "This $id id is not found"
                ]], null, JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
            );
        }
    }
}