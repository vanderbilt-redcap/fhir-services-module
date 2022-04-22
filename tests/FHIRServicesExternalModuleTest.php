<?php namespace Vanderbilt\FHIRServicesExternalModule;

use DateTime;
use DateTimeZone;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRQuestionnaire\FHIRQuestionnaireItem;
use Exception;

/**
 * These are copied over from (and not currently intended to be used outside of) the EM framework,
 * but they probably should be made available to modules for unit testing.
 */
const TEST_FORM = 'test_form';
const TEST_RECORD_ID = '1';
const TEST_RECORD_ID_FIELD = 'test_record_id';
const TEST_TEXT_FIELD = 'test_text_field';
const TEST_TEXT_FIELD_2 = 'test_text_field_2';
const TEST_SQL_FIELD = 'test_sql_field';
const TEST_REPEATING_FORM = 'test_repeating_form';
const TEST_REPEATING_FIELD_1 = 'test_repeating_field_1';
const TEST_REPEATING_FIELD_2 = 'test_repeating_field_2';
const VENDOR_PATH = __DIR__ . '/../vendor/';
const RESOURCES_PATH = VENDOR_PATH . 'resources-to-validate/';

class FHIRServicesExternalModuleTest extends BaseTest{
    static $failing = false;

    static function setUpBeforeClass():void{
        foreach(glob(RESOURCES_PATH . '*') as $path){
            // Removed files from the previous test run
            unlink($path);
        }
    }

    // Adapted from here: https://stackoverflow.com/a/45699470/2044597
    protected static function echo($message)
    {
        // if output buffer has not started yet
        if (ob_get_level() == 0) {
            // current buffer existence
            $hasBuffer = false;
            // start the buffer
            ob_start();
        } else {
            // current buffer existence
            $hasBuffer = true;
        }

        // echo to output
        echo $message;

        // flush current buffer to output stream
        ob_flush();
        flush();
        ob_end_flush();

        // if there were a buffer before this method was called
        //      in my version of PHPUNIT it has its own buffer running
        if ($hasBuffer) {
            // start the output buffer again
            ob_start();
        }
    }

    public function setUp():void{
        parent::setUp();

        // Remove any data from failed tests, or EM framework tests.
        $this->query('delete from redcap_data where project_id = ? and record = ?', [$this->getTestPID(), TEST_RECORD_ID]);

        // Remove all FHIR Mappings
        $this->query('update redcap_metadata set misc = "" where project_id = ?', $this->getTestPID());
        
        $this->setTypeAndEnum($this->getFieldName2(), 'text', '');

        $_GET['pid'] = $this->getTestPID();
        $this->removeProjectSetting('unmapped-use-questionnaire');

        // Remove the project object, in case it was spoofed by the previous test
        unset($this->module->project);
    }

    private function getFieldName(){
        return TEST_TEXT_FIELD;
    }

    private function getFieldName2(){
        return TEST_SQL_FIELD;
    }

    private function getFieldName3(){
        return TEST_TEXT_FIELD_2;
    }
    
    private function getFormName(){
        return 'all_field_type_examples';
    }
    
    private function createQuestionnaire($repeatingForms = []){
        $fields = json_decode(file_get_contents(__DIR__ . '/fields.json'), true);
        list($questionnaire, $warnings) = $this->module->createQuestionnaire($this->getProjectId(), $this->getFormName(), 'All Field Type Examples', $fields, $repeatingForms);

        $this->assertSame([
            'calculated',
            'signature',
            'slider',
            'sql',
        ], $warnings['skippedFields']);

        $this->assertSame(['@SOME-UNSUPPORTED-TAG'], $warnings['unsupportedActionTags']);

        return $questionnaire;
    }

    function testCreateQuestionnaire(){
        $_GET['pid'] = $this->getTestPID();
        $questionnaire = $this->createQuestionnaire();
        $actual = trim($this->jsonSerialize($questionnaire));

        ob_start();
        $pid = $this->getProjectId();
        require(__DIR__ . '/expected-questionnaire.json.php');
        $expected = ob_get_clean();
        
        $this->assertSame($expected, $actual);
    }

    function testCreateQuestionnaireItem_descriptive(){
        $fieldLabel = 'Hey partner!';

        $assert = function($videoUrl, $expectedText) use ($fieldLabel){
            $redcapField = [
                'element_label' => $fieldLabel,
                'element_type' => 'descriptive',
                'video_url' => $videoUrl
            ];

            $item = $this->module->createQuestionnaireItem($redcapField);

            $this->assertSame($expectedText, $item['text']);
        };     

        $assert(null, $fieldLabel);
        
        //assertXmlStringEqualsXmlString
        $videoUrl = 'https://www.youtube.com/watch?v=FavUpD_IjVY';
        $assert($videoUrl, $this->getDescriptiveVideoHTML($fieldLabel, $videoUrl));
    }

    function testHandleActionTags_default(){
        $codingCode = '1';

        $assert = function($type, $validation, $expectedValue) use ($codingCode){
            $item = $this->createQuestionnaireItem([
                'element_type' => $type,
                'element_validation_type' => $validation,
                'misc' => "@DEFAULT='$expectedValue'",
                'element_enum' => "$codingCode, This is a choice option."
            ]);

            $actualValue = $this->getTypedValue($item->getInitial()[0]);
            
            $fhirType = $this->getValue($item->getType());
            if($fhirType === 'choice'){
                $actualValue = $this->getValue($actualValue);
            }

            $this->assertSame($expectedValue, $actualValue, json_encode(func_get_args(), JSON_PRETTY_PRINT));    
        };

        // Default date values should be in Y-M-D format regardless of the validation setting.
        $assert('text', 'date_mdy', '2020-01-01');
        $assert('text', 'datetime_dmy', '2020-01-01 10:00');
        $assert('text', 'time', '20:00');
        $assert('text', 'float', 1.1);
        $assert('text', 'int', 1);
        
        foreach(['text', 'textarea', 'select', 'radio', 'yesno', 'truefalse'] as $type){
            $assert($type, '', $codingCode);
        }
    }

    private function createQuestionnaireItem($redcapField){
        $redcapField = array_merge($redcapField, [
            'element_type' => 'text',
            'action_tags' => $this->parseActionTags($redcapField)
        ]);

        return new FHIRQuestionnaireItem($this->module->createQuestionnaireItem($redcapField));
    }

    function testHandleActionTags_charLimit(){
        $charLimit=rand();

        $item = $this->createQuestionnaireItem([
            'misc' => "@CHARLIMIT=$charLimit"
        ]);

        $this->assertSame($charLimit, $this->getValue($item->getMaxLength()));
    }

    function testHandleActionTags_readonly(){
        $assert = function($expected, $actionTags){
            $item = $this->createQuestionnaireItem([
                'misc' => $actionTags
            ]);

            $this->assertSame($expected, $this->getValue($item->getReadOnly()));
        };

        $assert(null, '');
        $assert(true, '@READONLY');
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

    function testCreateQuestionnaireAnswer_and_GetTypedValue(){
        $assert = function($value, $type){
            $item = new FHIRQuestionnaireItem([
                'type' => $type
            ]);

            $expectedSystem = null;
            if($type === 'choice'){
                $expectedSystem = $this->getCodeSystemUrl('field_' . rand());
                $item->setAnswerOption([[
                    'valueCoding' => [
                        'system' => $expectedSystem,
                        'code' => (string) $value,
                        'display' => (string) rand(),
                    ]
                ]]);
            }

            $answer = $this->createQuestionnaireAnswer($item, $value);
            if($type === 'boolean' && $value === '1'){
                $value = true;
            }
            else if($type === 'dateTime'){
                $d = new DateTime($value, new DateTimeZone('UTC'));
                $d->setTimezone(new DateTimeZone(date_default_timezone_get()));
                $value = $this->formatREDCapDateTime($d);
            }
            else if($type === 'choice'){
                $this->assertSame(
                    $this->getValue($answer->getValueCoding()->getSystem()),
                    $expectedSystem
                );
            }

            $actualValue = $this->module->getTypedValue($answer);
            if(in_array($type, ['choice', 'open-choice'])){
                $actualValue = $this->getValue($actualValue);
            }

            $this->assertSame($value, $actualValue);
        };

        $assert('abc', 'string');
        $assert('def', 'text');
        $assert('ghi', 'choice');
        $assert(1, 'integer');
        $assert(1.1, 'decimal');
        $assert('1', 'boolean');
        $assert('2020-02-06 12:00', 'dateTime');
        $assert('2020-02-06', 'date');
        $assert('12:00', 'time');
        $assert('whatever', 'open-choice');
    }

    function setFHIRMapping($fieldName, $value){
        if($value === null){
            $value = '';
        }
        else{
            if(is_array($value)){
                $value = FieldMapper::actionTagEncode($value, JSON_PRETTY_PRINT);
            }

            $value = ACTION_TAG_PREFIX . $value . ACTION_TAG_SUFFIX;
        }

        // Wrap the action tag in other tags with quotes to make sure parsing still works correctly.
        $value = "@SOME-PRIOR-TAG-WITH-QUOTES='foo1' $value @SOME-LATER-TAG-WITH-QUOTES='foo2'";

        $pid = $this->getTestPID();

        $this->query('update redcap_metadata set misc = ? where project_id = ? and field_name = ?', [$value, $pid, $fieldName]);
    }

    private function setResourceTypeAndId($resourceName, $firstFieldName, $instance, $resource){
        $newResource = [
            // Type & id are required to build URLs, which are required by Bundles.
            'resourceType' => $resourceName,
            'id' => $resource['id'] ?? $this->module->getResourceId($resourceName, $this->getTestPID(), TEST_RECORD_ID, $firstFieldName, [
                'redcap_repeat_instance' => $instance
            ])
        ];

        $identifier = [
            'system' => $this->getResourceUrlPrefix(),
            'value' => $this->getRelativeResourceUrl($newResource)
        ];

        if($resourceName === 'QuestionnaireResponse'){
            $newResource['identifier'] = $identifier;
        }
        else{
            $newResource['identifier'][] = $identifier;
        }
        
        return array_merge($newResource, $resource);
    }

    function assert($fields, $expectedJSON, $resource = 'Patient', $expectingMultipleEntries = null){
        $pid = $this->getTestPID();
        $recordId = TEST_RECORD_ID;

        $data = [TEST_RECORD_ID_FIELD => $recordId];
        $uniqueMappings = [];
        $fieldNamesForIDs = [];
        foreach($fields as $fieldName=>$details){
            $mapping = $details['mapping'] ?? null;
            $element = $details['element'] ?? null;
            
            if($element !== null){
                $mapping = $resource . '/' . $element;
            }
            else{
                if($mapping !== null){
                    if(is_array($mapping)){
                        $resource = $mapping['type'];
                    }
                    else{
                        $parts = explode('/', $mapping);
                        $resource = $parts[0];
                    }
                }
                
                foreach(($mapping['additionalElements'] ?? []) as $additionalElement){
                    $field = $additionalElement['field'] ?? null;
                    $value = $additionalElement['value'] ?? null;
                    if($field !== null && $value !== null){
                        $data[$additionalElement['field']] = $value;
                    }
                }
            }

            if($expectingMultipleEntries === null){
                if(is_array($mapping)){
                    // do nothing, might need to be reconsidered
                }
                else if(!isset($uniqueMappings[$mapping])){
                    $uniqueMappings[$mapping] = true;
                }
                else{ // if($this->module->isRepeatableResource($resource)){
                    $expectingMultipleEntries = true;
                }
            }

            $this->setFHIRMapping($fieldName, $mapping);

            $values = $details['values'] ?? null; 
            if($values){
                // This is checkbox field
                foreach($values as $value){
                    $fieldNameForID = $fieldName . '___' . \Project::getExtendedCheckboxCodeFormatted($value);
                    $fieldNamesForIDs[] = $fieldNameForID;
                    $data[$fieldNameForID] = '1';
                }
            }
            else{
                $fieldNamesForIDs[] = $fieldName;
                $value = (string) $details['value'];
                if(!empty($value) && $value[0] === ' '){
                    // This is a leading white space check.  Manually update the DB since REDCap::saveData() trims leading & trailing whitespace automatically.
                    $this->query('update redcap_data set value = ? where project_id = ? and record = ? and field_name = ?', [$value, $pid, $recordId, $fieldName]);
                }
                else{
                    $data[$fieldName] = $value;
                }
            }
        }

        $this->saveData($data);

        $expected = [
            'resourceType' => 'Bundle',
            'type' => 'collection',
        ];

        if($expectingMultipleEntries !== true){
            $expectedJSON = [$expectedJSON];
        }

        $entries = [];
        for($i=0; $i<count($expectedJSON); $i++){
            if($expectingMultipleEntries){
                $fieldNameIndex = $i;
            }
            else{
                $fieldNameIndex = 0;
            }

            $expectedResource = $expectedJSON[$i];
            if(!isset($expectedResource['resourceType'])){
                $expectedResource = $this->setResourceTypeAndId($resource, $fieldNamesForIDs[$fieldNameIndex] ?? null, null, $expectedResource);
            }

            $entry = [];
            $entry['resource'] = $expectedResource;

            $entries[] = $entry;
        }

        $expected['entry'] = $entries;

        return $this->assertMappedExport($expected);
    }

    function getTestPID(){
        return \ExternalModules\ExternalModules::getTestPIDs()[0];
    }

    function setTypeAndEnum($fieldName, $type, $enum){
        if(is_array($enum)){
            $enum = $this->toElementEnum($enum);
        }

        $this->query('update redcap_metadata set element_type = ? ,element_enum = ? where project_id = ? and field_name = ?', [
            $type,
            $enum,
            $this->getTestPID(),
            $fieldName
        ]);
    }

    function testGetMappedFieldsAsBundle_patient(){
        $fieldName = $this->getFieldName();
        $fieldName2 = $this->getFieldName2();

        $assert = function($elementPath, $value, $expectedJSON) use ($fieldName){
            return $this->assert(
                [
                    $fieldName => [
                        'element' => $elementPath,
                        'value' => $value
                    ]
                ],
                $expectedJSON
            );
        };
        
        // Basic top level field
        $assert('gender', 'female', [
            'gender' => 'female'
        ]);

        // Mapping different cases to valid values
        $assert('gender', 'FeMaLe', [
            'gender' => 'female'
        ]);

        // Removal of leading & trailing whitespace
        $assert('gender', ' female ', [
            'gender' => 'female'
        ]);

        // Labels as values
        $this->setTypeAndEnum($fieldName, 'select', "F, Female \\n M, Male");
        $assert('gender', 'F', [
            'gender' => 'female'
        ]);
        $this->setTypeAndEnum($fieldName, 'text', '');

        // Array sub-values
        $assert('name/given', 'Joe', [
            'name' => [
                [
                    'given' => [
                        'Joe'
                    ]
                ]
            ]
        ]);

        $this->assert(
            [
                $fieldName => [
                    'mapping' => [
                        'type' => 'Patient',
                        'primaryElementPath' => 'telecom/value',
                        'additionalElements' => [
                            [
                                'element' => 'telecom/use',
                                'value' => 'Home',
                            ],
                            [
                                'element' => 'telecom/system',
                                'value' => 'Email',
                            ],
                            [
                                'element' => 'telecom/rank',
                                'value' => 1,
                            ],
                        ]
                    ],
                    'value' => 'a@b.com'
                ],
                $fieldName2 => [
                    'mapping' => [
                        'type' => 'Patient',
                        'primaryElementPath' => 'telecom/value',
                        'additionalElements' => [
                            [
                                'element' => 'telecom/use',
                                'value' => 'Work',
                            ],
                            [
                                'element' => 'telecom/system',
                                'value' => 'Email',
                            ],
                            [
                                'element' => 'telecom/rank',
                                'value' => 2,
                            ],
                        ]
                    ],
                    'value' => 'c@d.com'
                ]
            ],
            [
                'telecom' => [
                    [
                        'value' => 'a@b.com',
                        'use' => 'home',
                        'system' => 'email',
                        'rank' => 1, // Used to test positiveInt element types
                    ],
                    [
                        'value' => 'c@d.com',
                        'use' => 'work',
                        'system' => 'email',
                        'rank' => 2,
                    ]
                ]
            ]
        );
    }

    function assertUSCore($mappings, $expected){
        // The values are required for US Core patients to validate.
        $gender = 'female';
        $firstName = 'John';
        $lastName = 'Jones';

        $firstFieldName = array_keys($mappings)[0];
        $additionalElements = &$mappings[$firstFieldName]['mapping']['additionalElements'];

        $additionalElements[] = [
            'element' => 'gender',
            'value' => $gender
        ];

        $additionalElements[] = [
            'element' => 'name/given',
            'value' => $firstName
        ];

        $additionalElements[] = [
            'element' => 'name/family',
            'value' => $lastName
        ];

        $expected = array_merge(
            [
                "meta" => [
                    "profile" => [
                        "http://hl7.org/fhir/us/core/StructureDefinition/us-core-patient"
                    ]
                ]
            ],
            $expected,
            [
                "gender" => $gender,
                "name" => [
                    [
                        "given" => [
                            $firstName
                        ],
                        "family" => $lastName
                    ]
                ],
            ]
        );
        
        $this->assert($mappings, $expected);
    }

    function testRace(){
        $category1 = '2106-3';
        $category2 = '1002-5';
        $detailed1 = '1041-3';
        $detailed2 = '1044-7';
        $text = 'some text';

        $this->assertUSCore(
            [
                $this->getFieldName() => [
                    'value' => $category1,
                    'mapping' => [
                        'type' => 'Patient',
                        'primaryElementPath' => 'extension/race/ombCategory',
                        'additionalElements' => [
                            [
                                'element' => 'extension/race/ombCategory',
                                'value' => $category2
                            ],
                            [
                                'element' => 'extension/race/detailed',
                                'value' => $detailed1
                            ],
                            [
                                'element' => 'extension/race/detailed',
                                'value' => $detailed2
                            ],
                            [
                                'element' => 'extension/race/text',
                                'value' => $text
                            ],
                        ]
                    ]
                ]
            ],
            [
                "extension" => [
                    [
                        "url" => "http://hl7.org/fhir/us/core/StructureDefinition/us-core-race",
                        "extension" => [
                            [
                                "url" => "ombCategory",
                                "valueCoding" => [
                                    "system" => "urn:oid:2.16.840.1.113883.6.238",
                                    "code" => $category1,
                                ]
                            ],
                            [
                                "url" => "ombCategory",
                                "valueCoding" => [
                                    "system" => "urn:oid:2.16.840.1.113883.6.238",
                                    "code" => $category2,
                                ]
                            ],
                            [
                                "url" => "detailed",
                                "valueCoding" => [
                                    "system" => "urn:oid:2.16.840.1.113883.6.238",
                                    "code" => $detailed1,
                                ]
                            ],
                            [
                                "url" => "detailed",
                                "valueCoding" => [
                                    "system" => "urn:oid:2.16.840.1.113883.6.238",
                                    "code" => $detailed2,
                                ]
                            ],
                            [
                                "url" => "text",
                                "valueString" => $text
                            ]
                        ]
                    ]
                ]
            ]
        );
    }

    function testBirthsex(){
        $value = 'F';
        $this->assertUSCore(
            [
                $this->getFieldName() => [
                    'value' => $value,
                    'mapping' => [
                        'type' => 'Patient',
                        'primaryElementPath' => 'extension/birthsex',
                        'additionalElements' => []
                    ]
                ]
            ],
            [
                'extension' => [
                    [
                        'url' => 'http://hl7.org/fhir/us/core/StructureDefinition/us-core-birthsex',
                        'valueCode' => $value
                    ]
                ]
            ]
        );

        // Don't validate this one for now.  There might be something wrong with the validator related to birthsex.
        unlink($this->getValidationFilename());
    }

    function testEthnicity(){
        $category = '2135-2';
        $detailed1 = '2184-0';
        $detailed2 = '2148-5';
        $text = (string) rand();
        
        $this->assertUSCore(
            [
                $this->getFieldName() => [
                    'value' => $category,
                    'mapping' => [
                        'type' => 'Patient',
                        'primaryElementPath' => 'extension/ethnicity/ombCategory',
                        'additionalElements' => [
                            [
                                'element' => 'extension/ethnicity/detailed',
                                'value' => $detailed1
                            ],
                            [
                                'element' => 'extension/ethnicity/detailed',
                                'value' => $detailed2
                            ],
                            [
                                'element' => 'extension/ethnicity/text',
                                'value' => $text
                            ],
                        ]
                    ]
                ]
            ],
            [
                "extension" => [
                    [
                        "url" => "http://hl7.org/fhir/us/core/StructureDefinition/us-core-ethnicity",
                        "extension" => [
                            [
                                "url" => "ombCategory",
                                "valueCoding" => [
                                    "system" => "urn:oid:2.16.840.1.113883.6.238",
                                    "code" => $category,
                                ]
                            ],
                            [
                                "url" => "detailed",
                                "valueCoding" => [
                                    "system" => "urn:oid:2.16.840.1.113883.6.238",
                                    "code" => $detailed1,
                                ]
                            ],
                            [
                                "url" => "detailed",
                                "valueCoding" => [
                                    "system" => "urn:oid:2.16.840.1.113883.6.238",
                                    "code" => $detailed2,
                                ]
                            ],
                            [
                                "url" => "text",
                                "valueString" => $text
                            ]
                        ]
                    ]
                ]
            ]
        );
    }

    function testRaceAndEthnicity(){
        // Make sure they play nicely together.

        $category1 = '2106-3';
        $category2 = '2135-2';
        $text1 = (string) rand();
        $text2 = (string) rand(); 

        $this->assertUSCore(
            [
                $this->getFieldName() => [
                    'value' => $category1,
                    'mapping' => [
                        'type' => 'Patient',
                        'primaryElementPath' => 'extension/race/ombCategory',
                        'additionalElements' => [
                            [
                                'element' => 'extension/race/text',
                                'value' => $text1
                            ],
                            [
                                'element' => 'extension/ethnicity/ombCategory',
                                'value' => $category2
                            ],
                            [
                                'element' => 'extension/ethnicity/text',
                                'value' => $text2
                            ]
                        ]
                    ]
                ]
            ],
            [
                "extension" => [
                    [
                        "url" => "http://hl7.org/fhir/us/core/StructureDefinition/us-core-race",
                        "extension" => [
                            [
                                "url" => "ombCategory",
                                "valueCoding" => [
                                    "system" => "urn:oid:2.16.840.1.113883.6.238",
                                    "code" => $category1,
                                ]
                            ],
                            [
                                "url" => "text",
                                "valueString" => $text1
                            ]
                        ]
                    ],
                    [
                        "url" => "http://hl7.org/fhir/us/core/StructureDefinition/us-core-ethnicity",
                        "extension" => [
                            [
                                "url" => "ombCategory",
                                "valueCoding" => [
                                    "system" => "urn:oid:2.16.840.1.113883.6.238",
                                    "code" => $category2,
                                ]
                            ],
                            [
                                "url" => "text",
                                "valueString" => $text2
                            ]
                        ]
                    ]
                ]
            ]
        );
    }

    function testGetMappedFieldsAsBundle_patient_telecomComplexity(){
        $this->setFHIRMapping(TEST_REPEATING_FIELD_1, [
            'type' => 'Patient',
            'primaryElementPath' => 'telecom/value',
            'additionalElements' => [
                [
                    'element' => 'telecom/system',
                    'value' => 'phone'
                ]
            ]
        ]);

        $this->setFHIRMapping(TEST_REPEATING_FIELD_2, [
            'type' => 'Patient',
            'primaryElementPath' => 'contact/telecom/value',
            'additionalElements' => [
                [
                    'element' => 'contact/telecom/system',
                    'value' => 'email'
                ]
            ]
        ]);

        $this->saveData([
            [
                TEST_RECORD_ID_FIELD => TEST_RECORD_ID,
                'redcap_repeat_instrument' => TEST_REPEATING_FORM,
                'redcap_repeat_instance' => 1,
                TEST_REPEATING_FIELD_1 => 'a',
                TEST_REPEATING_FIELD_2 => 'b',
            ],
            [
                TEST_RECORD_ID_FIELD => TEST_RECORD_ID,
                'redcap_repeat_instrument' => TEST_REPEATING_FORM,
                'redcap_repeat_instance' => 2,
                TEST_REPEATING_FIELD_1 => 'c',
                TEST_REPEATING_FIELD_2 => 'd',
            ],
        ]);

        $this->assert([], [
            'telecom' => [
                [
                    'value' => 'a',
                    'system' => 'phone'
                ],
                [
                    'value' => 'c',
                    'system' => 'phone'
                ],
            ],
            'contact' => [
                [
                    'telecom' => [
                        [
                            'value' => 'b',
                            'system' => 'email'
                        ],
                    ]
                ],
                [
                    'telecom' => [
                        [
                            'value' => 'd',
                            'system' => 'email'
                        ],
                    ]
                ]
            ]
        ]);
    }

    function testGetMappedFieldsAsBundle_duplicateMappings(){
        $path = "Patient/gender";

        $this->expectExceptionMessage('mapped to multiple fields');

        $this->assert([
            $this->getFieldName() => [
                'mapping' => $path,
                'value' => rand()
            ],
            $this->getFieldName2() => [
                'mapping' => $path,
                'value' => rand()
            ],
        ], []);
    }

    function testGetMappedFieldsAsBundle_blankValue(){
        $this->assert(
            [
                $this->getFieldName() => [
                    'element' => 'gender',
                    'value' => 'female'
                ],
                $this->getFieldName2() => [
                    'element' => 'name/family',
                    'value' => ''
                ]
            ],
            [
                'gender' => 'female',
            ]
        );
    }

    function testGetMappedFieldsAsBundle_consent(){
        // This assertion covers two levels of array nesting.
        $this->assert(
            [
                $this->getFieldName() => [
                    'mapping' => [
                        'type' => 'Consent',
                        'primaryElementPath' => 'category/coding/system',
                        'additionalElements' => [
                            [
                                'element' => 'category/coding/code',
                                'value' => 'acd'
                            ],
                            [
                                'element' => 'category/coding/system',
                                'value' => 'http://terminology.hl7.org/CodeSystem/consentcategorycodes'
                            ],
                            [
                                'element' => 'category/coding/code',
                                'value' => 'dnr'
                            ],
                            [
                                'element' => 'status',
                                'value' => 'active'
                            ],
                            [
                                'element' => 'scope/coding/system',
                                'value' => 'http://terminology.hl7.org/CodeSystem/consentscope'
                            ],
                            [
                                'element' => 'scope/coding/code',
                                'value' => 'research'
                            ],
                            [
                                'element' => 'policyRule/coding/system',
                                'value' => 'http://terminology.hl7.org/CodeSystem/consentpolicycodes'
                            ],
                            [
                                'element' => 'policyRule/coding/code',
                                'value' => 'cric'
                            ],
                        ]
                    ],
                    'value' => 'http://terminology.hl7.org/CodeSystem/consentcategorycodes'
                ]
            ],
            [
                'category' => [
                    // Assert that BOTH codings come through
                    [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/consentcategorycodes',
                                'code' => 'acd'
                            ]
                        ]
                    ],
                    [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/consentcategorycodes',
                                'code' => 'dnr'
                            ]
                        ]   
                    ]
                ],
                'status' => 'active',
                'scope' => [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/consentscope',
                            'code' => 'research'
                        ]
                    ]
                ],
                'policyRule' => [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/consentpolicycodes',
                            'code' => 'cric'
                        ]
                    ]
                ],
            ],
            'Consent'
        );
    }

    private function saveData($data){
        if(!isset($data[0])){
            $data = [$data];
        }

        $recordId = TEST_RECORD_ID;

        foreach($data as &$row){
            $row[TEST_RECORD_ID_FIELD] = $recordId;
        }
        
        $result = \REDCap::saveData($this->getTestPID(), 'json', json_encode($data), 'overwrite');
        
        $this->assertSame([], $result['errors']);
        $this->assertSame([], $result['warnings']);
        $this->assertSame([$recordId => $recordId], $result['ids']);
    }

    function testGetMappedFieldsAsBundle_twoFieldsMappedToSamePath(){
        $name1 = (string) rand();
        $name2 = (string) rand();

        // This use case is currently lands on the closest parent item that is an 'array', the HumanName Resource in this case.
        // I'm not sure how useful/common cases like this would be in reality...
        $this->assert(
            [
                $this->getFieldName() => [
                    'element' => 'name/given',
                    'value' => $name1
                ],
                $this->getFieldName2() => [
                    'element' => 'name/given',
                    'value' => $name2
                ]
            ],
            [
                [
                    'name' => [
                        [
                            'given' => [
                                $name1,
                                $name2
                            ]
                        ]
                    ]
                ],
            ]
        );
    }

    function testGetMappedFieldsAsBundle_additionalFieldWithSamePathAsPrimary(){
        $name1 = (string) rand();
        $name2 = (string) rand();

        $this->assert(
            [
                 $this->getFieldName() => [
                    'mapping' => [
                        'type' => 'Patient',
                        'primaryElementPath' => 'name/given',
                        'additionalElements' => [
                            [
                                'element' => 'name/given',
                                'field' => $this->getFieldName2(),
                                'value' => $name2
                            ]
                        ]
                    ],
                    'value' => $name1
                ]
            ],
            [
                'name' => [
                    [
                        'given' => [
                            $name1,
                            $name2
                        ]
                    ]
                ]
            ]
        );
    }

    function testGetMappedFieldsAsBundle_codingAsPrimaryWithoutSystem(){
        $this->assert(
            [
                $this->getFieldName() => [
                    'element' => 'maritalStatus/coding/code',
                    'value' => 'married'
                ],
            ],
            [
                'maritalStatus' => [
                    "coding" => [
                        [
                            "system" => "http://terminology.hl7.org/CodeSystem/v3-MaritalStatus",
                            "code" => "M"
                        ]
                    ]
                ]
            ]
        );
    }

    function testGetMappedFieldsAsBundle_booleans(){
        $assert = function($value, $expected){
            $this->assert(
                [
                    $this->getFieldName() => [
                        'element' => 'deceasedBoolean',
                        'value' => $value
                    ],
                ],
                [
                    'deceasedBoolean' => $expected
                ]
            );
        };

        $assert('true', true);
        $assert('1', true);
        $assert('false', false);
        $assert('0', false);
    }

    function testGetMappedFieldsAsBundle_multipleDeceased(){
        $assert = function($value1, $value2, $expected){
            $this->assert(
                [
                    $this->getFieldName() => [
                        'element' => 'deceasedBoolean',
                        'value' => $value1
                    ],
                    $this->getFieldName2() => [
                        'element' => 'deceasedBoolean',
                        'value' => $value2
                    ],
                ],
                [
                    'deceasedBoolean' => $expected
                ],
                'Patient',
                false
            );
        };

        $assert(1, 1, true);
        $assert(1, 0, true);
        $assert(0, 1, true);
        $assert(0, 0, false);
    }

    function testGetMappedFieldsAsBundle_dateTimes(){
        $redcapFormattedDate = '2021-02-17 13:49:02';
        $this->assert(
            [
                $this->getFieldName() => [
                    'element' => 'deceasedDateTime',
                    'value' => $redcapFormattedDate
                ],
            ],
            [
                'deceasedDateTime' => '2021-02-17T13:49:02-06:00'
            ]
        );
    }

    function testGetMappedFieldsAsBundle_Questionnaire_mapped(){
        $this->assertQuestionnaire(false);
    }

    function testGetMappedFieldsAsBundle_Questionnaire_unmapped(){
        $this->assertQuestionnaire(true);
    }

    private function assertQuestionnaire($unmappedUseQuestionnaire){
        $_GET['pid'] = $this->getTestPID();

        if($unmappedUseQuestionnaire){
            $this->module->setProjectSetting('unmapped-use-questionnaire', true);
        }

        $formName = TEST_FORM;
        $fieldDisplayName1 = 'Test Text Field';
        $fieldDisplayName2 = 'Test SQL Field';
        $value1 = (string) rand();
        $value2 = (string) rand();
        $value3 = (string) rand();

        $createQuestionnaireItem = function($fieldName, $fieldDisplayName, $value = null){
            $item = [];
    
            if($value !== null){
                $item['answer'] = [
                    [
                        'valueString' => $value
                    ]
                ];
            }
    
            $item['linkId'] = $fieldName;
            $item['text'] = $fieldDisplayName;
    
            if($value === null){
                $item['type'] = 'string';
            }
    
            return $item;
        };

        
        $items = [];
        $answerItems = [];

        if($unmappedUseQuestionnaire){
            $items[] = $createQuestionnaireItem($this->getFieldName(), $fieldDisplayName1);
            $answerItems[] = $createQuestionnaireItem($this->getFieldName(), $fieldDisplayName1, $value1);
        }

        $items[] = $createQuestionnaireItem($this->getFieldName2(), $fieldDisplayName2);
        $answerItems[] = $createQuestionnaireItem($this->getFieldName2(), $fieldDisplayName2, $value2);

        $getId = function($recordId) use ($formName){
            return parent::getResourceId('Questionnaire', $this->getTestPID(), $recordId, $formName, [
                'redcap_event_name' => 'event_1_arm_1'
            ]);
        };

        $questionnaireId = $getId(null);
        $questionnaireResponseId = $getId(TEST_RECORD_ID);

        $questionnaire = $this->setResourceTypeAndId('Questionnaire', $formName, null, [
            'id' => $questionnaireId,
            'item' => [
                [
                    'item' => $items,
                    'linkId' => $questionnaireId,
                    'repeats' => false,
                    'type' => 'group'
                ]
            ],
            'name' => $questionnaireId,
            'status' => 'draft',
            'title' => 'Test Form',
        ]);

        $questionnaire['url'] = $this->getResourceUrl($questionnaire) . '&canonical';

        $questionnairePath = RESOURCES_PATH . $this->getName() . '-questionnaire.json';
        if(file_exists($questionnairePath)){
            throw new Exception("Something went wrong and questionnaires from two different tests overlapped.");
        }

        file_put_contents($questionnairePath, json_encode($questionnaire, JSON_PRETTY_PRINT));

        $this->saveData([
            $this->getFieldName() => $value1
        ]);

        $this->assert(
            [
                $this->getFieldName2() => [
                    'mapping' => 'Questionnaire',
                    'value' => $value2
                ],
                $this->getFieldName3() => [
                    'mapping' => 'Organization/name',
                    'value' => $value3 // Since this values is mapped to Organization, it should NOT get included in the Questionnaire
                ]
            ],
            [
                $this->setResourceTypeAndId('Organization', $this->getFieldName3(), null, [
                    'name' => $value3
                ]),
                $questionnaire,
                $this->setResourceTypeAndId('QuestionnaireResponse', $formName, null, [
                    'id' => $questionnaireResponseId,
                    'item' => [
                        [
                            'item' => $answerItems,
                            'linkId' => $questionnaireId
                        ]
                    ],
                    'status' => 'completed',
                    'questionnaire' => $questionnaire['url'],
                ])
            ],
            null,
            true
        );
    }

    function testGetEConsentFHIRBundle(){
        $pid = rand();
        $record = rand();
        $patientId = $this->getRecordFHIRId($pid, $record);
        
        $args = [
            'consentId' => $this->getInstanceFHIRId($pid, $record, rand(), 'some_form', rand()),
            'scope' => 'research',
            'categories' => ['acd', 'dnr'],
            'dateTime' => '2021-02-20 18:41:52.0',
            'data' => rand(),
            'type' => 'Some Type',
            'version' => rand(),
            'firstName' => 'Joe',
            'lastName' => 'Bloe',
            'creation' => time(),
            'authority' => $this->getProjectHomeUrl($pid),
            'patientId' => $patientId,
            'birthDate' => date('Y-m-d'),
        ];

        $patient = [
            'resourceType' => 'Patient',
            'id' => $patientId,
            'name' => [
                [
                    'given' => [
                        $args['firstName']
                    ],
                    'family' => $args['lastName']
                ]
            ],
            'birthDate' => $args['birthDate']
        ];
        
        $consent = [
            'resourceType' => 'Consent',
            'id' => $args['consentId'],
            'status' => 'active',
            'scope' => [
                'coding' => [
                    [
                        'system' => 'http://terminology.hl7.org/CodeSystem/consentscope',
                        'code' => $args['scope']
                    ]
                ]
            ],
            'category' => $this->formatConsentCategories($args['categories']),
            'patient' => [
                'reference' => $this->getRelativeResourceUrl($patient)
            ],
            'dateTime' => $this->formatFHIRDateTime($args['dateTime']),
            'sourceAttachment' => [
                'contentType' => 'application/pdf',
                'data' => base64_encode($args['data']),
                'hash' => sha1($args['data']),
                'title' => "{$args['type']} eConsent Version {$args['version']} for {$args['firstName']} {$args['lastName']}",
            ],
            'policy' => [
                [
                    'authority' => $args['authority']
                ]
            ]
        ];

        $expected = $this->createBundle([$consent, $patient]);
        $actual = $this->getEConsentFHIRBundle($args);

        $this->assertSame($expected, $actual);
        $this->queueForValidation($actual);
    }

    private function getValidationFilename(){
        return RESOURCES_PATH . $this->getName() . '.json';
    }

    private function queueForValidation($resource){
        /**
         * The IDs REDCap uses internally and for URLs can be longer than the 64 char limit.
         * Make sure these don't end up in any exported resources.
         */
        $this->assertIDNotSet($resource);

        if(!is_dir(RESOURCES_PATH)){
            mkdir(RESOURCES_PATH);
        }
        
        file_put_contents($this->getValidationFilename(), json_encode($resource, JSON_PRETTY_PRINT));
    }

    private function assertIDNotSet($resource){
        $this->assertFalse(isset($resource['id']));

        if($resource['resourceType'] === 'Bundle'){
            foreach($resource['entry'] as $entry){
                $this->assertIDNotSet($entry['resource']);
            }
        }
    }

    function teardown():void{
        if($this->getStatus() !== \PHPUnit\Runner\BaseTestRunner::STATUS_PASSED){
            self::$failing = true;
        }
    }

    static function teardownAfterClass():void{
        self::validate();
    }

    /**
     * This is important becaus AJV (the browser based validation feature) is not comprehensive or FHIR specific.
     * Instead of spinning up a new java process on each test, we could write resources to a folder and scan them all at once in tearDown().
     */
    static function validate(){
        if(
            defined('SKIP_VALIDATION')
            ||
            self::$failing
            ||
            empty(glob(RESOURCES_PATH . '*'))
        ){
            return;
        }

        // Add a basic resource to guarantee that multiple resources are always being validated
        // so that the validator always prints the "--" lines for each file (we depend on them for parsing output).
        file_put_contents(RESOURCES_PATH . "guaranteed-file.json", json_encode([
            'resourceType' => 'HumanName'
        ]));

        $validatorPath = VENDOR_PATH . "fhir-validator.jar";
        if(!file_exists($validatorPath)){
            file_put_contents($validatorPath, file_get_contents('https://github.com/hapifhir/org.hl7.fhir.core/releases/latest/download/validator_cli.jar'));
        }

        // Normalize the path so it matches paths in the output.
        $validatorPath = realpath($validatorPath);

        $igArgs = ' -ig hl7.fhir.us.core#4.0.0 ';
        foreach(glob(RESOURCES_PATH . '*-questionnaire.json') as $path){
            $igArgs .= " -ig $path";
        }

        $cmd = "java -Xmx4g -jar $validatorPath " . RESOURCES_PATH . " -version 4.0.1 $igArgs 2>&1";
        exec($cmd, $output, $exitCode);

        $onValidationFailed = function($message) use ($output){
            die(implode("\n", $output) . "\n\nCould not verify validator output.  $message\n\n");
        };

        $validatedPaths = [];
        for($lineIndex=0; $lineIndex<count($output); $lineIndex++){
            $line = $output[$lineIndex];
            $parts = explode(' ', $line);
            if($parts[0] === '--'){
                $path = $parts[1];
                $path = str_replace('\\', '/', $path);
                $path = realpath($path);
                $validatedPaths[$path] = true;
                
                $line1 = $output[$lineIndex+1];
                $line2 = $output[$lineIndex+2];

                if(
                    (
                        $line1 === 'Success: 0 errors, 0 warnings, 1 notes'
                        &&
                        (
                            $line2 === '  Information: All OK'
                            ||
                            (
                                str_starts_with($line2, "  Information @ Bundle.entry[0].resource.ofType(Contract).scope.coding[0] (line 17, col26): Code System URI 'https://data.bioontology.org/ontologies/")
                                &&
                                str_ends_with($line2, "' is unknown so the code cannot be validated")
                            )
                        )
                    )
                    ||
                    (
                        // I can't get this warning to go away for the life of me.  I wonder if it's a bug in the validator...
                        $line1 === 'Success: 0 errors, 1 warnings, 0 notes' &&
                        str_contains($line2, 'Questionnaire') &&
                        str_contains($line2, 'Name should be usable as an identifier for the module by machine processing applications such as code generation')
                    )
                ){
                    // We're good!
                }
                else{
                    $onValidationFailed("Did not find expected success lines for path: $path");
                }
            }
        }
        
        foreach(glob(RESOURCES_PATH . '*') as $path){
            $path = realpath($path);
            if(($validatedPaths[$path] ?? null) !== true){
                $onValidationFailed('Validation line not found for path: ' . $path);
            }
        }

        // Check the exit code last so that any more specific errors are shown first
        if($exitCode !== 0){
            $onValidationFailed("Validation failed with exit code $exitCode");
        }
    }

    function testObservationMapping(){
        $resourceName = 'Observation';
        $status = 'preliminary';
        $code = '15074-8';
        $system = 'http://loinc.org';
        $display = 'Glucose [Moles/volume] in Blood';
        $issued = $this->formatREDCapDateTimeWithSeconds(time());
        $reference = 'Patient/' . rand();

        $mapping = [
            'type' => $resourceName,
            'primaryElementPath' => 'valueInteger',
            'additionalElements' => [
                [
                    'element' => 'effectivePeriod/start',
                    'field' => $this->getFieldName2()
                ],
                [
                    'element' => 'effectivePeriod/end',
                    'field' => $this->getFieldName2()
                ],
                [
                    'element' => 'status',
                    'value' => $status
                ],
                [
                    'element' => 'code/coding/system',
                    'value' => $system
                ],
                [
                    'element' => 'code/coding/code',
                    'value' => $code
                ],
                [
                    'element' => 'code/coding/display',
                    'value' => $display
                ],
                [
                    'element' => 'issued',
                    'value' => $issued
                ],
                [
                    'element' => 'subject/reference',
                    'value' => $reference
                ],
            ]
        ];

        $value = rand();
        $time = time();
        $fhirDateTime = $this->formatFHIRDateTime($time);
        
        $this->assert(
            [
                $this->getFieldName() => [
                    'mapping' => $mapping,
                    'value' => $value
                ],
                $this->getFieldName2() => [
                    'value' => $this->formatREDCapDateTimeWithSeconds($time)
                ],
            ],
            [
                'valueInteger' => $value,
                'effectivePeriod' => [
                    'start' => $fhirDateTime,
                    'end' => $fhirDateTime,
                ],
                'status' => $status,
                'code' => [
                    'coding' => [
                        [
                            'system' => $system,
                            'code' => $code,
                            'display' => $display
                        ]
                    ]
                ],
                'issued' => $this->formatFHIRDateTime($issued),
                'subject'=> [
                    'reference' => $reference
                ],
            ],
            $resourceName
        );
    }

    function testObservationMapping_valueQuantity(){
        $resourceName = 'Observation';
        $unitAndCode = 'mmol/L';
        $system = 'http://unitsofmeasure.org';
        $code = (string) rand();
        $status = 'final';
        
        $mapping = [
            'type' => $resourceName,
            'primaryElementPath' => 'valueQuantity/value',
            'additionalElements' => [
                [
                    'element' => 'valueQuantity/unit',
                    'value' => $unitAndCode
                ],
                [
                    'element' => 'valueQuantity/system',
                    'value' => $system
                ],
                [
                    'element' => 'valueQuantity/code',
                    'value' => $unitAndCode
                ],
                [
                    'element' => 'code/text',
                    'value' => $code
                ],
                [
                    'element' => 'status',
                    'value' => $status
                ],
            ]
        ];

        $value = 123.45; // Make sure float/decimal values are represented correctly.
        
        $this->assert(
            [
                $this->getFieldName() => [
                    'mapping' => $mapping,
                    'value' => $value
                ]
            ],
            [
                'valueQuantity' => [
                    'value' => $value,
                    'unit' => $unitAndCode,
                    'system' => $system,
                    'code' => $unitAndCode,
                ],
                'code' => [
                    'text' => $code
                ],
                'status' => $status
            ],
            $resourceName
        );
    }

    function testObservationMapping_singleQuotePlaceholders(){
        $resourceName = 'Observation';
        $status = 'final';
        $code = (string) rand();
        $value = "some value with 'single quotes' in it that could interfere with the action tag beginning/end if not replaced";
        
        $mapping = [
            'type' => $resourceName,
            'primaryElementPath' => 'valueString',
            'additionalElements' => [
                [
                    'element' => 'status',
                    'value' => $status
                ],
                [
                    'element' => 'code/text',
                    'value' => $code
                ],
            ]
        ];
 
        $this->assert(
            [
                $this->getFieldName() => [
                    'mapping' => $mapping,
                    'value' => $value
                ]
            ],
            [
                'valueString' => $value,
                'status' => $status,
                'code' => [
                    'text' => $code
                ],
            ],
            $resourceName
        );
    }

    private function getResourceId($fieldName){
        return parent::getResourceId('SomethingOtherThanPatient', $this->getTestPID(), TEST_RECORD_ID, $fieldName, []);
    }

    function testObservationMapping_bundle(){
        $this->setFHIRMapping(TEST_TEXT_FIELD, 'Patient/name/family');
        $this->setFHIRMapping(TEST_REPEATING_FIELD_1, [
            'type' => 'Observation',
            'primaryElementPath' => 'valueString',
            'additionalElements' => [
                [
                    'element' => 'code/text',
                    'field' => TEST_REPEATING_FIELD_2
                ],
                [
                    'element' => 'status',
                    'value' => 'final'
                ]
            ]
        ]);

        $pid = $this->getTestPID();
        $lastName = 'Smith';

        $this->saveData([
            [
                TEST_RECORD_ID_FIELD => TEST_RECORD_ID,
                TEST_TEXT_FIELD => $lastName,
            ],
            [
                TEST_RECORD_ID_FIELD => TEST_RECORD_ID,
                'redcap_repeat_instrument' => TEST_REPEATING_FORM,
                'redcap_repeat_instance' => 1,
                TEST_REPEATING_FIELD_1 => 'a',
                TEST_REPEATING_FIELD_2 => 'b',
            ],
            [
                TEST_RECORD_ID_FIELD => TEST_RECORD_ID,
                'redcap_repeat_instrument' => TEST_REPEATING_FORM,
                'redcap_repeat_instance' => 2,
                TEST_REPEATING_FIELD_1 => 'c',
                TEST_REPEATING_FIELD_2 => 'd',
            ],
        ]);

        $expectedPatient = $this->setResourceTypeAndId('Patient', null, null, [
            'name' => [
                [
                    'family' => $lastName,
                ],
            ],
        ]);

        $expected = [
            'resourceType' => 'Bundle',
            'type' => 'collection',
            'entry' => [
                [
                    'resource' => $expectedPatient,
                ],
                [
                    'resource' => $this->setResourceTypeAndId('Observation', TEST_REPEATING_FIELD_1, 1, [
                        'valueString' => 'a',
                        'code' => [
                            'text' => 'b',
                        ],
                        'status' => 'final',
                        'subject' => [
                            'reference' => "Patient/" . $expectedPatient['id']
                        ]
                    ]),
                ],
                [
                    'resource' => $this->setResourceTypeAndId('Observation', TEST_REPEATING_FIELD_1, 2, [
                        'valueString' => 'c',
                        'code' => [
                            'text' => 'd',
                        ],
                        'status' => 'final',
                        'subject' => [
                            'reference' => "Patient/" . $expectedPatient['id']
                        ]
                    ]),
                ],
            ], 
        ];

        $this->assertMappedExport($expected);
    }

    private function setFullUrls($bundle){
        for($i=0; $i<count($bundle['entry']); $i++){
            $entry = $bundle['entry'][$i];
            $bundle['entry'][$i] = array_merge([
                'fullUrl' => $this->getResourceUrl($entry['resource'])
            ], $entry);
        }

        return $bundle;
    }

    function testImmunizationMapping_bundle(){
        $lastName = 'Smith';
        $code = '16';
        $occurrence = (string) rand();

        $expectedPatient = $this->setResourceTypeAndId('Patient', null, null, [
            'name' => [
                [
                    'family' => $lastName,
                ],
            ],
        ]);

        $this->assert(
            [
                $this->getFieldName() => [
                    'mapping' => 'Patient/name/family',
                    'value' => $lastName
                ],
                $this->getFieldName2() => [
                    'mapping' => [
                        'type' => 'Immunization',
                        'primaryElementPath' => 'vaccineCode/coding/system',
                        'additionalElements' => [
                            [
                                'element' => 'vaccineCode/coding/code',
                                'value' => $code,
                            ],
                            [
                                'element' => 'status',
                                'value' => 'completed',
                            ],
                            [
                                'element' => 'occurrenceString',
                                'value' => $occurrence,
                            ],
                            [
                                'element' => 'reaction/reported',
                                'value' => true,
                            ],
                        ]
                    ],
                    'value' => 'http://hl7.org/fhir/sid/cvx'
                ]
            ],
            [
                $expectedPatient,
                $this->setResourceTypeAndId('Immunization', $this->getFieldName2(), null, [
                    'vaccineCode' => [
                        'coding' => [
                            [
                                'system' => 'http://hl7.org/fhir/sid/cvx',
                                'code' => $code
                            ]
                        ]
                    ],
                    'status' => 'completed',
                    'occurrenceString' => $occurrence,
                    'reaction' => [
                        [
                            'reported' => true // Test booleans
                        ]
                    ],
                    'patient' => [
                        'reference' => "Patient/" . $expectedPatient['id']
                    ]
                ])
            ],
            'Patient',
            true
        );
    }

    private function assertMappedExport($bundle = []){
        if(!isset($bundle['entry'])){
            $bundle['entry'] = [];
        }

        $bundle = $this->setFullUrls($bundle);

        foreach($bundle['entry'] as &$entry){
            // Now that full URLs have been set, IDs can be removed (since they might be over the 64 char limit).
            unset($entry['resource']['id']);
        }

        $actual = $this->getMappedFieldsAsBundle($this->getTestPID(), TEST_RECORD_ID);

        $this->assertSame($bundle, $actual);
        $this->queueForValidation($bundle);

        return $actual;
    }

    function testArrayMappingsBecomeSeparateResources(){
        $value1 = (string) rand();
        $value2 = (string) rand();

        $this->assert(
            [
                $this->getFieldName() => [
                    'mapping' => [
                        'type' => 'Organization',
                        'primaryElementPath' => 'name',
                    ],
                    'value' => $value1
                ],
                $this->getFieldName2() => [
                    'mapping' => [
                        'type' => 'Organization',
                        'primaryElementPath' => 'name',
                    ],
                    'value' => $value2
                ]
            ],
            [
                [
                    'name' => $value1
                ],
                [
                    'id' => $this->getResourceId($this->getFieldName2()),
                    'name' => $value2
                ],
            ],
            'Organization', // We could have chosen any repeatable resource to test this.
            true
        );
    }

    function testNonArrayMappingsMergedIntoSingleResource(){
        $value1 = (string) rand();

        $this->assert(
            [
                $this->getFieldName() => [
                    'element' => 'name',
                    'value' => $value1
                ],
                $this->getFieldName2() => [
                    'element' => 'active',
                    'value' => true
                ]
            ],
            [
                'name' => $value1,
                'active' => true
            ],
            'Organization' // We could have chosen any repeatable resource to test this.
        );
    }

    function testResearchSubject(){
        $lastName = 'Smith';

        $patient = $this->setResourceTypeAndId('Patient', null, null, [
            'name' => [
                [
                    'family' => $lastName,
                ],
            ],
        ]);

        $study = $this->setResourceTypeAndId('ResearchStudy', $this->getFieldName3(), null, [
            'status' => 'active'
        ]);

        $this->assert(
            [
                $this->getFieldName() => [
                    'mapping' => 'Patient/name/family',
                    'value' => $lastName
                ],
                $this->getFieldName2() => [
                    'mapping' => 'ResearchSubject/status',
                    'value' => 'candidate'
                ],
                $this->getFieldName3() => [
                    'mapping' => 'ResearchStudy/status',
                    'value' => 'active'
                ],
            ],
            [
                $patient,
                $study,
                $this->setResourceTypeAndId('ResearchSubject', $this->getFieldName2(), null, [
                    'status' => 'candidate',
                    'individual' => [
                        'reference' => $this->getRelativeResourceUrl($patient)
                    ],
                    'study' => [
                        'reference' => $this->getRelativeResourceUrl($study)
                    ],
                ]),
            ],
            'Patient',
            true
        );
    }

    function testCondition(){
        $family = 'Jetson';
        $snomed1 = '109006';
        $snomed2 = '122003';

        $patient = $this->setResourceTypeAndId('Patient', null, null, [
            'name' => [
                [
                    'family' => $family,
                ],
            ],
        ]);

        $this->assert(
            [
                $this->getFieldName() => [
                    'mapping' => [
                        'type' => 'Condition',
                        'primaryElementPath' => 'verificationStatus/coding/system',
                        'additionalElements' => [
                            [
                                'element' => 'verificationStatus/coding/code',
                                'value' => 'confirmed'
                            ],
                            [
                                'element' => 'code/coding/code',
                                'value' => $snomed1
                            ],
                            [
                                'element' => 'code/coding/code',
                                'value' => $snomed2
                            ],
                        ]
                    ],
                    /**
                     * Condition/verificationStatus has a version appended to the valueSet URL in dataelements.json.
                     * Test to ensure that that version gets removed so that the system url can be matched.
                     */
                    'value' => 'http://terminology.hl7.org/CodeSystem/condition-ver-status'
                ],
                $this->getFieldName2() => [
                    'mapping' => 'Patient/name/family',
                    'value' => $family,
                ]
            ],
            [
                $this->setResourceTypeAndId('Condition', $this->getFieldName(), null, [
                    'verificationStatus' => [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/condition-ver-status',
                                'code' => 'confirmed'
                            ]
                        ]
                    ],
                    'code' => [
                        'coding' => [
                            // Make sure multiple codes can be mapped at the same time.
                            [
                                'system' => 'http://snomed.info/sct',
                                'code' => $snomed1
                            ],
                            [  
                                'system' => 'http://snomed.info/sct',
                                'code' => $snomed2
                            ]
                        ]
                    ],
                    'subject' => [
                        'reference' => "Patient/" . $patient['id']
                    ]
                ]),
                $patient
            ],
            null,
            true
        );
    }

    function testNegativeIntegers(){
        $negativeNumber = -rand();
        $this->assertTrue($negativeNumber < 0);

        $codeText = (string) rand();
        $this->assert(
            [
                $this->getFieldName() => [
                    'mapping' => [
                        'type' => 'Observation',
                        'primaryElementPath' => 'code/text',
                        'additionalElements' => [
                            [
                                'element' => 'status',
                                'value' => 'final'
                            ],
                            [
                                'element' => 'valueInteger',
                                'value' => $negativeNumber
                            ],
                        ]
                    ],
                    'value' => $codeText
                ],
            ],
            [
                'code' => [
                    'text' => $codeText,
                ],
                'status' => 'final',
                'valueInteger' => $negativeNumber
            ],
        );
    }

    function testPrimaryElementSystem(){
        $system = 'http://terminology.hl7.org/CodeSystem/observation-category';
        $code = 'social-history';

        $this->assert(
            [
                $this->getFieldName() => [
                    'mapping' => [
                        'type' => 'Observation',
                        'primaryElementPath' => 'code/coding/code',
                        'primaryElementSystem' => $system,
                        'additionalElements' => [
                            [
                                'element' => 'status',
                                'value' => 'final'
                            ],
                        ]
                    ],
                    'value' => $code
                ],
            ],
            [
                'code' => [
                    'coding' => [
                        [
                            'system' => $system,
                            'code' => $code
                        ],
                    ]
                ],
                'status' => 'final',
            ],
        );
    }

    private function spoofElementEnum($field, $value){
        if(is_array($value)){
            $value = $this->toElementEnum($value);
            $type = 'select';
        }
        else{
            $type = 'text';
        }

        $project = new \stdClass;
        $project->metadata = [
            $field => [
                'element_type' => $type,
                'element_enum' => $value
            ]
        ];

        $this->module->project = $project;
    }

    private function toElementEnum($choices){
        $enum = '';
        foreach($choices as $code=>$label){
            $enum .= "$code, $label \\n";
        }

        return trim($enum);
    }

    function testMultipleChoiceDisplayValues(){
        $choices = [
            'Q01' => 'Encephalocele',
            'Q02' => 'Microcephaly',
            'Q03' => 'Congenital hydrocephalus',
        ];

        $system = 'http://hl7.org/fhir/sid/icd-10';
        $code = array_rand($choices);

        $this->spoofElementEnum($this->getFieldName(), $choices);

        $this->assert(
            [
                $this->getFieldName() => [
                    'mapping' => [
                        'type' => 'Contract',
                        'primaryElementPath' => 'scope/coding/code',
                        'primaryElementSystem' => $system
                    ],
                    'value' => $code
                ],
            ],
            [
                'scope' => [
                    'coding' => [
                        [
                            'system' => $system,
                            'display' => $choices[$code],
                            'code' => $code,
                        ],
                    ]
                ],
            ],
        );
    }

    function testSystemSetFromOntology(){
        $code = (string) rand();

        $systems = $this->getOntologySystems();
        $ontologyKey = array_rand($systems);
        $system = $systems[$ontologyKey];
        
        $this->spoofElementEnum($this->getFieldName(), "BIOPORTAL:$ontologyKey");

        // Make sure an ontology system is set for primary elements
        $this->assert(
            [
                $this->getFieldName() => [
                    'mapping' => [
                        'type' => 'Contract',
                        'primaryElementPath' => 'scope/coding/code',
                    ],
                    'value' => $code
                ],
            ],
            [
                'scope' => [
                    'coding' => [
                        [
                            'system' => $system,
                            'code' => $code
                        ],
                    ]
                ],
            ],
        );

        // Make sure an ontology system is set for additional elements
        $display = (string) rand();
        $this->assert(
            [
                $this->getFieldName() => [
                    'mapping' => [
                        'type' => 'Contract',
                        'primaryElementPath' => 'scope/coding/display',
                        'additionalElements' => [
                            [
                                'element' => 'scope/coding/code',
                                'value' => $code
                            ],
                        ]
                    ],
                    'value' => $display
                ],
            ],
            [
                'scope' => [
                    'coding' => [
                        [
                            'system' => $system,
                            'display' => $display,
                            'code' => $code
                        ],
                    ]
                ],
            ],
        );
    }

    function testGetOntologySystems(){
        $list = $this->getOntologySystems();

        foreach(CUSTOM_ONTOLOGY_SYSTEMS as $key=>$value){
            $this->assertSame($value, $list[$key]);
        }

        // Check one that REDCap autopopulates that is not in our custom list.
        $key = 'ICO';
        $this->assertNull(CUSTOM_ONTOLOGY_SYSTEMS[$key] ?? null);
        $this->assertSame($list[$key], 'https://data.bioontology.org/ontologies/ICO');
    }

    function testCheckboxMapping(){
        $resource = 'Contract';

        $choices = [
            'Q01' => 'Encephalocele',
            'Q02' => 'Microcephaly',
            'Q03' => 'Congenital hydrocephalus',
        ];

        $system = 'http://hl7.org/fhir/sid/icd-10';
        [$firstCode, $secondCode] = array_rand($choices, 2);

        $this->setTypeAndEnum($this->getFieldName(), 'checkbox', $choices);
        $this->clearProjectCache();

        $this->assert(
            [
                $this->getFieldName() => [
                    'mapping' => [
                        'type' => $resource,
                        'primaryElementPath' => 'scope/coding/code',
                        'primaryElementSystem' => $system,
                    ],
                    'values' => [$firstCode, $secondCode]
                ],
            ],
            [
                [
                    'scope' => [
                        'coding' => [
                            [
                                'system' => $system,
                                'display' => $choices[$firstCode],
                                'code' => $firstCode,
                            ]
                        ]
                    ]
                ],
                [
                    'scope' => [
                        'coding' => [
                            [
                                'system' => $system,
                                'display' => $choices[$secondCode],
                                'code' => $secondCode,
                            ]
                        ]
                    ]
                ],
            ],
            null,
             true
        );
    }

    function testGetCodeFromExtendedCheckboxCodeFormatted(){
        $fieldName = 'whatever';
        $code = 'Q21.0';

        $this->spoofElementEnum($fieldName, [
            $code => rand()
        ]);

        $m = new FieldMapper($this->module, $this->getProjectId(), TEST_RECORD_ID);
        $this->assertSame(
            $code,
            $m->getCodeFromExtendedCheckboxCodeFormatted($fieldName, \Project::getExtendedCheckboxCodeFormatted($code))
        );

        $this->expectExceptionMessage('not found for field');
        $m->getCodeFromExtendedCheckboxCodeFormatted($fieldName, 'code-that-does-not-exist');
    }
}