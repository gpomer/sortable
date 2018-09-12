<?php namespace Jedrzej\Sortable;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class Criterion
{
    const ORDER_ASCENDING = 'asc';
    const ORDER_DESCENDING = 'desc';

    protected $field;

    protected $order;

    /**
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @return string
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * Creates criterion object for given value.
     *
     * @param string $value query value
     * @param string $defaultOrder default sort order if order is not given explicitly in query
     *
     * @return Criterion
     */
    public static function make($value, $defaultOrder = self::ORDER_ASCENDING)
    {
        $value = static::prepareValue($value);
        list($field, $order) = static::parseFieldAndOrder($value, $defaultOrder);

        return new static($field, $order);
    }

    /**
     * Applies criterion to query.
     *
     * @param Builder $builder query builder
     */
    public function apply(Builder $builder)
    {
        $sortMethod = 'sort' . studly_case($this->getField());

        if(method_exists($builder->getModel(), $sortMethod)) {
            call_user_func_array([$builder->getModel(), $sortMethod], [$builder, $this->getOrder()]);
        } else if(strstr($this->getField(),'.')) {
            $relation_keys = explode(".",$this->getField());
            // main table[0] . join table[1] . foreign key[2] . sort column[3] . ?flip[4]?
            if(isset($relation_keys[4]) && $relation_keys[4]=='flip') {
                // use foreign key in primary table
                if(!collect($builder->getQuery()->joins)->pluck('table')->contains($relation_keys[1])) {
                    $builder->leftJoin($relation_keys[1], $relation_keys[0].'.'.$relation_keys[2], '=', $relation_keys[1].'.id');
                }
                $builder->orderBy($relation_keys[1].'.'.$relation_keys[3], $this->getOrder())
                    ->select($relation_keys[0].'.*');       // just to avoid fetching anything from joined table
            } else {
                // use foreign key in joined table
                if (! collect($builder->getQuery()->joins)->pluck('table')->contains($relation_keys[1])) {
                    $builder->leftJoin($relation_keys[1], $relation_keys[1] . '.' . $relation_keys[2], '=', $relation_keys[0] . '.id');
                }
                $builder->orderBy($relation_keys[1] . '.' . $relation_keys[3], $this->getOrder())
                    ->select($relation_keys[0] . '.*');       // just to avoid fetching anything from joined table
            }
        }else {
            $builder->orderBy($this->getField(), $this->getOrder());
        }
    }

    /**
     * @param string $field field name
     * @param string $order sort order
     */
    protected function __construct($field, $order)
    {
        if (!in_array($order, [static::ORDER_ASCENDING, static::ORDER_DESCENDING])) {
            throw new InvalidArgumentException('Invalid order value');
        }

        $this->field = $field;
        $this->order = $order;
    }

    /**
     *  Cleans value and converts to array if needed.
     *
     * @param string $value value
     *
     * @return string
     */
    protected static function prepareValue($value)
    {
        return trim($value, " \t\n\r\0\x0B");
    }

    /**
     * Parse query parameter and get field name and order.
     *
     * @param string $value
     * @param string $defaultOrder default sort order if order is not given explicitly in query
     *
     * @return string[]
     *
     * @throws InvalidArgumentException when unable to parse field name or order
     */
    protected static function parseFieldAndOrder($value, $defaultOrder)
    {
        if (preg_match('/^([^,]+)(,(asc|desc))?$/', $value, $match)) {
            return [$match[1], isset($match[3]) ? $match[3] : $defaultOrder];

        }

        throw new InvalidArgumentException(sprintf('Unable to parse field name or order from "%s"', $value));
    }
}
