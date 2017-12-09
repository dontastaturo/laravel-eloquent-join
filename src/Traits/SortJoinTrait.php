<?php

namespace Fico7489\Laravel\SortJoin\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait SortJoinTrait
{
    use ExtendRelationsTrait;

    private $selected = false;
    private $joinedTables = [];

    private $relationClauses = [];
    private $softDelete = 'withoutTrashed';

    public function scopeSetWhereForJoin(Builder $builder, $column, $operator = null, $value = null, $boolean = 'and')
    {
        $this->relationClauses[] = ['column' => $column, 'operator' => $operator, 'value' => $value, 'boolean' => $boolean];
    }

    public function scopeSetOrWhereForJoin(Builder $builder, $column, $operator = null, $value)
    {
        $this->relationClauses[] = ['column' => $column, 'operator' => $operator, 'value' => $value, 'boolean' => 'or'];
    }

    public function scopeSetSoftDelete(Builder $builder, $param)
    {
        $this->softDelete = $param;
    }


    public function scopeWhereJoin(Builder $builder, $column, $operator = null, $value = null, $boolean = 'and')
    {
        $column = $this->performJoin($builder, $column);
        return $builder->where($column, $operator, $value, $boolean);
    }

    public function scopeOrWhereJoin(Builder $builder, $column, $operator = null, $value)
    {
        $column = $this->performJoin($builder, $column);
        return $builder->orWhere($column, $operator, $value);
    }

    public function scopeOrderByJoin(Builder $builder, $column, $sortBy = 'asc')
    {
        $column = $this->performJoin($builder, $column);
        return $builder->orderBy($column, $sortBy);
    }


    private function performJoin($builder, $relations)
    {
        $relations = explode('.', $relations);

        $column = end($relations);
        $baseTable = $this->getTable();
        $baseModel = $this;

        $currentTable = $this->getTable();
        $currentModel = $this;

        foreach ($relations as $relation) {
            if ($relation == $column) {
                //last item in $relations argument is sort|where column
                continue;
            }

            $relatedRelation = $currentModel->$relation();
            $relatedModel = $relatedRelation->getRelated();
            $relatedPrimaryKey = $relatedModel->primaryKey;
            $relatedTable = $relatedModel->getTable();

            if (array_key_exists($relation, $this->joinedTables)) {
                $relatedTableAlias = $this->joinedTables[$relation];
            } else {
                $relatedTableAlias = uniqid();

                if ($relatedRelation instanceof BelongsTo) {
                    $keyRelated = $relatedRelation->getForeignKey();

                    $builder->leftJoin($relatedTable . ' as ' . $relatedTableAlias, $relatedTableAlias . '.' . $relatedPrimaryKey, '=', $currentTable . '.' . $keyRelated);
                } elseif ($relatedRelation instanceof HasOne) {
                    $keyRelated = $relatedRelation->getQualifiedForeignKeyName();

                    $keyRelated = last(explode('.', $keyRelated));
                    $builder->leftJoin($relatedTable . ' as ' . $relatedTableAlias, $relatedTableAlias . '.' . $keyRelated, '=', $currentTable . '.' . $relatedPrimaryKey);
                }

                if (method_exists($relatedModel, 'getQualifiedDeletedAtColumn')) {
                    if ($this->softDelete == 'withTrashed') {
                        //do nothing
                    } elseif ($this->softDelete == 'withoutTrashed') {
                        $builder->where($relatedTableAlias . '.deleted_at', '=', null);
                    } elseif ($this->softDelete == 'onlyTrashed') {
                        $builder->where($relatedTableAlias . '.deleted_at', '<>', null);
                    }
                }

                foreach ($relatedModel->relationClauses as $relationClause) {
                    $builder->where($relationClause['column'], $relationClause['operator'], $relationClause['value'], $relationClause['boolean']);
                }
            }

            $currentTable = $relatedTableAlias;
            $currentModel = $relatedModel;

            $this->joinedTables[$relation] = $relatedTableAlias;
        }

        if (! $this->selected) {
            $this->selected = true;
            $builder->select($baseTable . '.*')->groupBy($baseTable . '.' . $baseModel->primaryKey);
        }

        return $currentTable . '.' . $column;
    }
}
