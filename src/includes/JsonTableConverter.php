<?php


class JsonTableConverter
{
    public static function saveJsonFromTabData($data, $fileName=false)
    {
        if (count($data) == 0) {
            return;
        }

        $jsonData = array();
        foreach ($data as $field => $dataRows) {
            if (empty($dataRows)) {
                Output::print_msg("[RAW] empty data set for [" . $field . "]", "WARNING");
                continue;
            }

            foreach ($dataRows as $dataRow) {
                if (!isset($dataRow[0]) && count($dataRow) == 1) {
                    $jsonData[$field] = json_encode(self::dataToHash($dataRow));
                } else {
                    $jsonData[$field][] = json_encode(self::dataToHash($dataRow));
                }

            }

            $jsonData = self::buildArray($jsonData);

            $jsonDataResult = implode("\n", array_map(function($elem) { return json_encode($elem); }, $jsonData[$field]));
            $file = ($fileName) ? $fileName : 'raw-result.json';
            $file = $field . '-' . $file;
            Output::print_msg("[RAW] Saving json in FILE: " . Input::INPUT_OUTPUT_FOLDER . '/' . $file, "INFO");
            file_put_contents(Input::INPUT_OUTPUT_FOLDER . '/' . $file, $jsonDataResult);
        }
    }

    private static function dataToHash($data)
    {
        $result = array();

        if (count($data) == 0) {
            return array();
        }

        foreach ($data as $field => $value) {
            if (strpos($field, '.') === false) {
                $result[$field] = $value;
            } else {
                $fieldParts = explode('.', $field);
                $head = array_shift($fieldParts);
                $newKey = implode('.',$fieldParts);

                if (!array_key_exists($head, $result)) {
                    $result[$head] = array();
                }

                $result[$head] = array_merge_recursive(self::dataToHash(array($newKey => $value)), $result[$head]);
            }
        }

        return $result;
    }

    public static function buildArray($data)
    {
        $result = $finalResult = $groupedByField = [];

        foreach ($data as $tableIndex => $rows) {
            foreach ($rows as $arrayNumericKey => $jsonRow) {
                $result[$tableIndex][$arrayNumericKey] = json_decode($jsonRow, true);
                if (preg_match("/.*\[(.*)\].*/", implode(array_keys($result[$tableIndex][$arrayNumericKey])), $matches)) {
                    $groupedByField[$tableIndex] = $matches[1];
                    continue;
                }
            }
        }

        foreach ($result as $tableIndex => $rows) {

            $arrayIndex = 0;
            foreach ($rows as $numericKeyIndex => $row) {

                if (empty($groupedByField)) {
                    $finalResult[$tableIndex][$numericKeyIndex] = $row;
                    continue;
                }

                foreach ($row as $field => $value) {
                    $matches = [];
                    if (preg_match("/(.*)\[(.*)\](.*)/", $field, $matches)) {
                        $field = $matches[1];
                        $groupedBy = $matches[2];
                        $attribute = $matches[3];

                        if (!array_key_exists($tableIndex, $finalResult)) {
                            $finalResult[$tableIndex] = [];
                        }

                        if (!array_key_exists($row[$groupedBy], $finalResult[$tableIndex])) {
                            $finalResult[$tableIndex][$row[$groupedBy]] = [];
                        }

                        if (!array_key_exists($field, $finalResult[$tableIndex][$row[$groupedBy]])) {
                            $finalResult[$tableIndex][$row[$groupedBy]][$field] = [];
                        }

                        $finalResult[$tableIndex][$row[$groupedBy]][$field][$arrayIndex][$attribute] = $value;
                    } else {
                        $finalResult[$tableIndex][$row[$groupedByField[$tableIndex]]][$field] = $value;
                    }
                }
                $arrayIndex++;
            }
        }

        return $finalResult;
    }
}