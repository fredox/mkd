<?php
/*
 *  group>conditionalQuery    The group will be included in the import if the query in Destination env returns empty
 *
 *  group<conditionalQuery    The group will be included in the import if the query in Origin env returns empty
 *
 *  group<>conditionalQuery   The group wil be included in the import if the query return empty in both envs
 */


class Condition {

    const EXECUTE_IN_SOURCE_POSITIVE = 1;
    const EXECUTE_IN_SOURCE_NEGATIVE = 2;
    const EXECUTE_IN_DESTINATION_POSITIVE = 3;
    const EXECUTE_IN_DESTINATION_NEGATIVE = 4;
    const EXECUTE_IN_BOTH_POSITIVE = 5;
    const EXECUTE_IN_BOTH_NEGATIVE = 6;
    const EXECUTE_IN_BOTH_SOURCE_POSITIVE_DESTINATION_NEGATIVE = 7;
    const EXECUTE_IN_BOTH_SOURCE_NEGATIVE_DESTINATION_POSITIVE = 8;
    const EXECUTE_NOT_SUPPORTED = 9;

    public static $conditionRegexp = "/([^<>!]+)(<>|!<>|<>!|!<>!|<|!<|>|>!)+([^<>!]+)/";

    public static function isConditionalIndex($index): bool
    {
        return preg_match(self::$conditionRegexp, $index) === 1;
    }

    public static function getIndex($conditionalIndex): string
    {
        return self::getConditionPieces($conditionalIndex)['index'];
    }

    public static function getQuery($conditionalGroup): string
    {
        return self::getConditionPieces($conditionalGroup)['query'];
    }

    public static function getConditionType($groupIndex): int
    {
        $conditionType = self::getConditionPieces($groupIndex)['conditionType'];

        switch ($conditionType) {
            case ">":
            return self::EXECUTE_IN_DESTINATION_POSITIVE;
            break;
            case "<":
                return self::EXECUTE_IN_SOURCE_POSITIVE;
                break;
            case "<>":
                return self::EXECUTE_IN_BOTH_POSITIVE;
                break;
            case ">!":
                return self::EXECUTE_IN_DESTINATION_NEGATIVE;
                break;
            case "!<":
                return self::EXECUTE_IN_SOURCE_NEGATIVE;
                break;
            case "!<>":
                return self::EXECUTE_IN_BOTH_SOURCE_NEGATIVE_DESTINATION_POSITIVE;
                break;
            case "<>!":
                return self::EXECUTE_IN_BOTH_SOURCE_POSITIVE_DESTINATION_NEGATIVE;
                break;
            case "!<>!":
                return self::EXECUTE_IN_BOTH_NEGATIVE;
                break;
            default:
                return self::EXECUTE_NOT_SUPPORTED;
        }
    }

    public static function getConditionPieces($index)
    {
        $result = preg_match(self::$conditionRegexp, $index, $matches);
        if ($result) {
            $index = $matches[1];
            $query = $matches[3];
            $conditionType = $matches[2];

            return [
                'index' => $index,
                'query' => $query,
                'conditionType' => $conditionType
            ];
        }

        return false;
    }

    public static function conditionPass($groupIndex, Queryable $sourceEnvironment, Queryable $destinationEnvironment, $queries, $key=false): bool
    {
        $conditionalType = self::getConditionType($groupIndex);

        $queryIndex = self::getQuery($groupIndex);
        $query = $queries[$queryIndex];

        if ($key) {
            $query = str_replace('@KEY', $key, $query);
        }


        $rowsSource = $sourceEnvironment->query($query, true);
        $rowsDestination = $destinationEnvironment->query($query, true);

        switch ($conditionalType) {
            case self::EXECUTE_IN_DESTINATION_POSITIVE:
                return !empty($rowsDestination);
            case self::EXECUTE_IN_SOURCE_POSITIVE:
                return !empty($rowsSource);
            case self::EXECUTE_IN_DESTINATION_NEGATIVE:
                return empty($rowsDestination);
            case self::EXECUTE_IN_SOURCE_NEGATIVE:
                return empty($rowsSource);
            case self::EXECUTE_IN_BOTH_NEGATIVE:
                return (empty($rowsDestination) && empty($rowsSource));
            case self::EXECUTE_IN_BOTH_POSITIVE:
                return (!empty($rowsDestination) && !empty($rowsSource));
            case self::EXECUTE_IN_BOTH_SOURCE_POSITIVE_DESTINATION_NEGATIVE:
                return (!empty($rowsDestination) && empty($rowsSource));
            case self::EXECUTE_IN_BOTH_SOURCE_NEGATIVE_DESTINATION_POSITIVE:
                return (empty($rowsDestination) && !empty($rowsSource));
            default:
                throw new Exception('Conditional group type not supported');
        }
    }
}