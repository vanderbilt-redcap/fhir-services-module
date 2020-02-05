<?php namespace Vanderbilt\FHIRServicesExternalModule;

require_once __DIR__ . '/../../../redcap_connect.php';

use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRQuestionnaire\FHIRQuestionnaireItem;

class FHIRServicesExternalModuleTest extends \ExternalModules\ModuleBaseTest{
    private function getFormName(){
        return 'all_field_type_examples';
    }
    
    private function createQuestionnaire($repeatingForms = []){
        $fields = json_decode(file_get_contents(__DIR__ . '/fields.json'), true);
        list($questionnaire, $skippedFields) = $this->module->createQuestionnaire(116, $this->getFormName(), 'All Field Type Examples', $fields, $repeatingForms);
        return $questionnaire;
    }

    function testCreateQuestionnaire(){
        $questionnaire = $this->createQuestionnaire();
        $actual = trim($this->jsonSerialize($questionnaire));

        ob_start();
        require(__DIR__ . '/expected-questionnaire.json.php');
        $expected = ob_get_clean();
        
        $this->assertSame($expected, $actual);
    }

    function testCreateQuestionnaireItem_descriptive(){
        $fieldLabel = 'Hey partner!';

        $assert = function($videoUrl, $expectedText) use ($fieldLabel){
            $item = $this->createQuestionnaireItem([
                'element_label' => $fieldLabel,
                'element_type' => 'descriptive',
                'video_url' => $videoUrl
            ]);

            $this->assertSame($expectedText, $item['text']);
        };     

        $assert(null, $fieldLabel);
        
        //assertXmlStringEqualsXmlString
        $videoUrl = 'https://www.youtube.com/watch?v=FavUpD_IjVY';
        $assert($videoUrl, $this->getDescriptiveVideoHTML($fieldLabel, $videoUrl));
    }

    function testHandleAnnotations_charLimit(){
        $charLimit=rand();

        $field = [
            'misc' => "@SOME-TAG @CHARLIMIT=$charLimit @SOME-OTHER-TAG"
        ];

        $item = [];

        $this->module->handleAnnotations($field, $item);
        
        $item = new FHIRQuestionnaireItem($item);


        $this->assertSame($charLimit, $this->getValue($item->getMaxLength()));
    }

    function testRepeatingForms(){
        $assert = function($isRepeating){
            $repeatingForms = [];
            if($isRepeating){
                $repeatingForms[] = $this->getFormName();
            }

            $questionnaire = $this->createQuestionnaire($repeatingForms);
            $value = $this->module->getValue($questionnaire->getItem()[0]->getRepeats());
            $this->assertSame($isRepeating, $value);
        };

        $assert(true);
        $assert(false);
    }

    
}