<?php namespace Vanderbilt\FHIRServicesExternalModule;

require_once __DIR__ . '/../../../redcap_connect.php';

class QuestionnaireExportTest extends \ExternalModules\ModuleBaseTest{
    function testCreateQuestionnaire(){
        $fields = json_decode(file_get_contents(__DIR__ . '/fields.json'), true);
        list($questionnaire, $skippedFields) = $this->createQuestionnaire(116, 'all_field_type_examples', 'All Field Type Examples', $fields);
        $actual = trim($this->jsonSerialize($questionnaire));
        $expected = trim(file_get_contents(__DIR__ . '/expected-questionnaire.json'));
        
        $this->assertSame($expected, $actual);
    }

    }
}