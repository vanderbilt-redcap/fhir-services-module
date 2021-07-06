<?php namespace Vanderbilt\FHIRServicesExternalModule;

use DateTime;
use DateTimeZone;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRQuestionnaire\FHIRQuestionnaireItem;

/**
 * These are not intended to be used outside the EM framework,
 * but let's bend that rule a bit for convenience unless/until it becomes a problem.
 */
const TEST_RECORD_ID = '1';
const TEST_RECORD_ID_FIELD = 'test_record_id';
const TEST_TEXT_FIELD = 'test_text_field';
const TEST_SQL_FIELD = 'test_sql_field';
const TEST_REPEATING_FORM = 'test_repeating_form';
const TEST_REPEATING_FIELD_1 = 'test_repeating_field_1';
const TEST_REPEATING_FIELD_2 = 'test_repeating_field_2';

class FHIRServicesExternalModuleTest extends BaseTest{
    public function setUp():void{
        parent::setUp();

        $this->setFHIRMapping($this->getFieldName(), null);
        $this->setFHIRMapping($this->getFieldName2(), null);
        $this->setFHIRMapping(TEST_REPEATING_FIELD_1, null);
        $this->setFHIRMapping(TEST_REPEATING_FIELD_2, null);
        
        $this->setTypeAndEnum($this->getFieldName2(), 'text', '');
    }

    private function getFieldName(){
        return TEST_TEXT_FIELD;
    }

    private function getFieldName2(){
        return TEST_SQL_FIELD;
    }
    
    private function getFormName(){
        return 'all_field_type_examples';
    }
    
    private function createQuestionnaire($repeatingForms = []){
        $fields = json_decode(file_get_contents(__DIR__ . '/fields.json'), true);
        list($questionnaire, $warnings) = $this->module->createQuestionnaire(116, $this->getFormName(), 'All Field Type Examples', $fields, $repeatingForms);

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

    function testGetTypedValue(){
        $assert = function($value, $type){
            $item = new FHIRQuestionnaireItem([
                'type' => $type
            ]);

            $answer = $this->createQuestionnaireAnswer($item, $value);
            if($type === 'boolean' && $value === '1'){
                $value = true;
            }
            else if($type === 'dateTime'){
                $d = new DateTime($value, new DateTimeZone('UTC'));
                $d->setTimezone(new DateTimeZone(date_default_timezone_get()));
                $value = $this->formatREDCapDateTime($d);
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

    function assert($fields, $expectedJSON, $resource = 'Patient', $expectingMultipleEntries = null){
        $pid = $this->getTestPID();
        $recordId = 1;

        $data = [TEST_RECORD_ID_FIELD => $recordId];
        $uniqueMappings = [];
        foreach($fields as $fieldName=>$details){
            $mapping = @$details['mapping'];
            $element = @$details['element'];
            
            if($element !== null){
                $mapping = $resource . '/' . $element;
            }
            else{                
                foreach($mapping['additionalElements'] as $additionalElement){
                    $field = @$additionalElement['field'];
                    $value = @$additionalElement['value'];
                    if($field !== null && $value !== null){
                        $data[$additionalElement['field']] = $value;
                    }
                }
            }

            if($expectingMultipleEntries === null){
                if(!isset($uniqueMappings[$mapping])){
                    $uniqueMappings[$mapping] = true;
                }
                else{ // if($this->module->isRepeatableResource($resource)){
                    $expectingMultipleEntries = true;
                }
            }

            $this->setFHIRMapping($fieldName, $mapping);

            $value = (string) $details['value'];
            if($value[0] === ' '){
                // This is a leading white space check.  Manually update the DB since REDCap::saveData() trims leading & trailing whitespace automatically.
                $this->query('update redcap_data set value = ? where project_id = ? and record = ? and field_name = ?', [$value, $pid, $recordId, $fieldName]);
            }
            else{
                $data[$fieldName] = $value;
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
        foreach($expectedJSON as $expectedResource){
            $expectedResource = array_merge([
                // Type & id are required to build URLs, which are required by Bundles.
                'resourceType' => $resource,
                'id' => $this->getRecordFHIRId($pid, $recordId)
            ], $expectedResource);

            $entry = [];
            $entry['fullUrl'] = $this->getResourceUrl($expectedResource);
    
            $entry['resource'] = $expectedResource;

            $entries[] = $entry;
        }

        $expected['entry'] = $entries;
        
        $actual = $this->getMappedFieldsAsBundle($pid, $recordId);
        
        $this->assertSame($expected, $actual);

        // TODO - Make this work on all calls.  AJV is not comprehensive.
        // Instead of spinning up a new java process on each test, we could write resources to a files and scan them all at once in tearDown().
        // $this->validate($actual);

        return $actual;
    }

    function getTestPID(){
        return \ExternalModules\ExternalModules::getTestPIDs()[0];
    }

    function setTypeAndEnum($fieldName, $type, $enum){
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

    function testGetMappedFieldsAsBundle_patient_telecomComplexity(){
        $this->setFHIRMapping(TEST_REPEATING_FIELD_1, [
            'type' => 'Patient',
            'primaryElementPath' => 'telecom/value',
        ]);

        $this->setFHIRMapping(TEST_REPEATING_FIELD_2, [
            'type' => 'Patient',
            'primaryElementPath' => 'contact/telecom/value',
        ]);

        $pid = $this->getTestPID();
        $recordId = TEST_RECORD_ID;

        $this->saveData([
            [
                TEST_RECORD_ID_FIELD => $recordId,
                'redcap_repeat_instrument' => TEST_REPEATING_FORM,
                'redcap_repeat_instance' => 1,
                TEST_REPEATING_FIELD_1 => 'a',
                TEST_REPEATING_FIELD_2 => 'b',
            ],
            [
                TEST_RECORD_ID_FIELD => $recordId,
                'redcap_repeat_instrument' => TEST_REPEATING_FORM,
                'redcap_repeat_instance' => 2,
                TEST_REPEATING_FIELD_1 => 'c',
                TEST_REPEATING_FIELD_2 => 'd',
            ],
        ]);

        $this->assert([], [
            'telecom' => [
                ['value' => 'a'],
                ['value' => 'c'],
            ],
            'contact' => [
                [
                    'telecom' => [
                        ['value' => 'b'],
                        ['value' => 'd'],
                    ]
                ]
            ]
        ]);
    }

    function testGetMappedFieldsAsBundle_duplicateMappings(){
        $path = "Patient/gender";
        foreach([$this->getFieldName(), $this->getFieldName2()] as $fieldName){
            $this->setFHIRMapping($fieldName, $path);
        }

        $this->expectExceptionMessage('currently mapped to multiple fields');
        $this->getMappedFieldsAsBundle($this->getTestPID(), 1);
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
                    'element' => 'category/coding/display',
                    'value' => 'foo'
                ]
            ],
            [
                'category' => [
                    [
                        'coding' => [
                            [
                                'display' => 'foo'
                            ]
                        ]
                    ]
                ]
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
                            ]
                        ],
                        [
                            'given' => [
                                $name2,
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

    function testGetMappedFieldsAsBundle_codeableConcept(){
        $this->assert(
            [
                $this->getFieldName() => [
                    'element' => 'maritalStatus',
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
        $this->validate($actual);
    }

    function validate($resource){
        if(defined('SKIP_VALIDATION')){
            return;
        }

        $vendorPath = __DIR__ . '/../vendor';

        $validatorPath = realpath("$vendorPath/fhir-validator.jar");
        if(!file_exists($validatorPath)){
            file_put_contents($validatorPath, file_get_contents('https://storage.googleapis.com/ig-build/org.hl7.fhir.validator.jar'));
        }

        $resourcePath = realpath("$vendorPath/resource-to-validate.json");

        $resourceJson = json_encode($resource, JSON_PRETTY_PRINT);
        file_put_contents($resourcePath, $resourceJson);
        
        if($resource['resourceType'] === 'Patient'){
            $profileArg = '-ig hl7.fhir.us.core -profile http://hl7.org/fhir/us/core/StructureDefinition/us-core-patient';
        }
        else{
            $profileArg = '';
        }

        $cmd = "java -Xmx3g -jar $validatorPath $resourcePath -version 4.0.1 $profileArg 2>&1";
        exec($cmd, $output, $exitCode);
        if($exitCode !== 0){
            throw new \Exception("Validation of the following resource failed with the following output:\n\n$resourceJson\n\n" . implode("\n", $output));
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
                    'element' => 'code/coding/code',
                    'value' => $code
                ],
                [
                    'element' => 'code/coding/system',
                    'value' => $system
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
                            'code' => $code,
                            'system' => $system,
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

        // The Observation examples on hl7.org have the id of the associated Patient.
        $patientAndObservationId = $this->getRecordFHIRId($pid, TEST_RECORD_ID);

        $expectedPatient = [
            'resourceType' => 'Patient',
            'id' => $patientAndObservationId,
            'name' => [
                [
                    'family' => $lastName,
                ],
            ],
        ];

        $expected = [
            'resourceType' => 'Bundle',
            'type' => 'collection',
            'entry' => [
                [
                    'fullUrl' => $this->getResourceUrl($expectedPatient),
                    'resource' => $expectedPatient,
                ],
                [
                    'resource' => [
                        'resourceType' => 'Observation',
                        'id' => $patientAndObservationId,
                        'valueString' => 'a',
                        'code' => [
                            'text' => 'b',
                        ],
                        'status' => 'final',
                    ],
                ],
                [
                    'resource' => [
                        'resourceType' => 'Observation',
                        'id' => $patientAndObservationId,
                        'valueString' => 'c',
                        'code' => [
                            'text' => 'd',
                        ],
                        'status' => 'final',
                    ],
                ],
            ], 
        ];

        $this->assertSame($expected,  $this->getMappedFieldsAsBundle($pid, TEST_RECORD_ID));
        $this->validate($expected);
    }

    function testImmunizationMapping_bundle(){
        $this->setFHIRMapping($this->getFieldName(), 'Patient/name/family');
        $this->setFHIRMapping($this->getFieldName2(), 'Immunization/vaccineCode');

        $pid = $this->getTestPID();
        $lastName = 'Smith';
        $code = 16;

        $this->saveData([
            [
                TEST_RECORD_ID_FIELD => TEST_RECORD_ID,
                $this->getFieldName() => $lastName,
                $this->getFieldName2() => $code,
            ]
        ]);

        $patientId = $this->getRecordFHIRId($pid, TEST_RECORD_ID);

        $expectedPatient = [
            'resourceType' => 'Patient',
            'id' => $patientId,
            'name' => [
                [
                    'family' => $lastName,
                ],
            ],
        ];

        $expected = [
            'resourceType' => 'Bundle',
            'type' => 'collection',
            'entry' => [
                [
                    'fullUrl' => $this->getResourceUrl($expectedPatient),
                    'resource' => $expectedPatient,
                ],
                [
                    'resource' => [
                        'resourceType' => 'Immunization',
                        'id' => $patientId, // This probably isn't kosher, but we'll make the id match the Patient like Observations for now.
                        'vaccineCode' => [
                            'coding' => [
                                [
                                    'system' => 'http://hl7.org/fhir/sid/cvx',
                                    'code' => (string) $code // make sure this is a string!
                                ]
                            ]
                        ],
                        'patient' => [
                            'reference' => "Patient/$patientId"
                        ]
                    ],
                ],
            ], 
        ];

        $this->assertSame($expected,  $this->getMappedFieldsAsBundle($pid, TEST_RECORD_ID));
        // $this->validate($expected);
    }

    function testImmunization_booleans(){
        $value = true;
        
        $this->assert(
            [
                $this->getFieldName() => [
                    'mapping' => 'Immunization/reaction/reported',
                    'value' => $value
                ]
            ],
            [
                'reaction' => [
                    [
                        'reported' => $value
                    ]
                ],
            ],
            'Immunization'
        );
    }

    function testMultipleInstancesOfRepeatableResources(){
        $this->assert(
            [
                $this->getFieldName() => [
                    'element' => 'clinicalStatus',
                    'value' => 'active'
                ],
                $this->getFieldName2() => [
                    'element' => 'clinicalStatus',
                    'value' => 'recurrence'
                ]
            ],
            [
                [
                    'clinicalStatus' => 'active'
                ],
                [
                    'clinicalStatus' => 'recurrence'
                ],
            ],
            'SomeRepeatableResource'
        );
    }
}