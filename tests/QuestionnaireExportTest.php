<?php namespace Vanderbilt\FHIRServicesExternalModule;

class QuestionnaireExportTest{
    static function run($module){
        $fields = json_decode(file_get_contents(__DIR__ . '/fields.json'), true);
        list($questionnaire, $skippedFields) = $module->createQuestionnaire(116, 'all_field_type_examples', 'All Field Type Examples', $fields);
        $actual = trim($module->jsonSerialize($questionnaire));
        $expected = trim(file_get_contents(__DIR__ . '/expected-questionnaire.json'));
        
        if($actual !== $expected){
            header('Content-type: text/plain'); // Prevent rendering as HTML in browsers 
            die("Unit Test Failure: The following JSON does not match the expected JSON bundled with the module:\n\n$actual");
        }
    }
}