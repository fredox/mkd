<?php

include_once('PgsqlToPgsqlMonad.php');

class SerializeddatafileToPgsqlMonad extends PgsqlToPgsqlMonad {
    /**
     * @param $data
     * @param SerializedDataFileEnvironment|Environment $sourceEnvironment
     * @param Environment|MysqlEnvironment $targetEnvironment
     * @param array $transformations
     * @return array
     */
    public function bind($data, Environment $sourceEnvironment, Environment $targetEnvironment, $transformations = array())
    {
        if (!empty($transformations)) {
            foreach ($transformations as $transformation) {
                Output::print_msg("[TRANSFORMATIONS] Applying Transformation " . get_class($transformation), "INFO");
                $data = $transformation->transform($data, $sourceEnvironment, $targetEnvironment);
            }
        }

        $targetEnvironment->rawQueries = $sourceEnvironment->getRawQueries();

        return $this->unit($data, $targetEnvironment);
    }
}