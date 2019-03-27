<?php

/**
 * OWM (Object-Whatever Mapper) for the "model" module and more.
 *
 * Provide mixins to work on result lists.
 */

namespace EPFL\Results;

/**
 * "use" this trait in your class to get a find() method implemented
 * in terms of an already existing all() method. Works both for a
 * static ::all() method or a ->all() instance method.
 */
trait FindFromAllTrait
{
    /**
     * @return A ResultSet instance
     */
    function find ($criteria_array) {
        $found = array();
        foreach ((isset($this) ? $this->all() : static::all()) as $that) {
            foreach ($criteria_array as $k => $v) {
                $matcher = _Matcher::make($v);
                $getter = "get_$k";
                if (method_exists($that, $getter)) {
                    if (! $matcher->matches($that->$getter())) continue 2;
                } elseif (method_exists($that, 'meta') and
                          $that->meta()->has($k)) {
                    if (! $matcher->matches($that->meta()->$getter())) continue 2;
                } else {
                    if (! $matcher->matches($that->$k)) continue 2;
                }
            }
            $found[] = $that;
        }
        return new InMemoryResultSet($found);
    }
}

// Some day, we will have various matching operators and the ability
// to project them SQL-side.
class _Matcher
{
    protected function __construct () {}

    static function make($what) {
        $thisclass = get_called_class();
        $that = new $thisclass();
        // For now, only matching for equality is supported.
        $that->equals = $what;
        return $that;
    }

    function matches ($value) {
        return $value === $this->equals;
    }

    // TODO: Introduce some kind of way to ->apply(), or something,
    // onto a _WPQueryBuilder. I guess one of them or us must be
    // public, or something.
}


// Some day, we will have SQL-constructing ResultSet's.
interface ResultSet
{
    public function all ();
    public function first_preferred ($criteria_array);
}


class InMemoryResultSet implements ResultSet
{
    function __construct ($results) {
        $this->results = $results;
    }

    use FindFromAllTrait;

    function all () {
        return $this->results;
    }

    function first_preferred ($criteria_array) {
        $preferred = $this->find($criteria_array)->all();
        if (count($preferred)) {
            return $preferred[0];
        } else if (count($this->results)) {
            return $this->results[0];
        } else {
            return NULL;
        }
    }
}
