<?php namespace Vanderbilt\FHIRServicesExternalModule;

use DateTime;
use DateTimeZone;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRQuestionnaire\FHIRQuestionnaireItem;

class FHIRServicesExternalModuleTest extends BaseTest{
    public function setUp():void{
        parent::setUp();

        $this->setFHIRMapping($this->getFieldName(), null);
        $this->setFHIRMapping($this->getFieldName2(), null);
        
        $this->setTypeAndEnum($this->getFieldName2(), 'text', '');
    }

    private function getFieldName(){
        return 'test_text_field';
    }

    private function getFieldName2(){
        return 'test_sql_field';
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
            $value = ACTION_TAG_PREFIX . $value . ACTION_TAG_SUFFIX;
        }

        $pid = $this->getTestPID();

        $this->query('update redcap_metadata set misc = ? where project_id = ? and field_name = ?', [$value, $pid, $fieldName]);
    }

    function assert($fields, $expectedJSON, $resource = 'Patient'){
        $pid = $this->getTestPID();
        $recordId = 1;

        $data = ['test_record_id' => $recordId];
        foreach($fields as $fieldName=>$details){
            $this->setFHIRMapping($fieldName, $resource . '/' . $details['element']);

            $value = (string) $details['value'];
            if($value[0] === ' '){
                // This is a leading white space check.  Manually update the DB since REDCap::saveData() trims leading & trailing whitespace automatically.
                $this->query('update redcap_data set value = ? where project_id = ? and record = ? and field_name = ?', [$value, $pid, $recordId, $fieldName]);
            }
            else{
                $data[$fieldName] = $value;
            }
        }

        \REDCap::saveData($pid, 'json', json_encode([$data]), 'overwrite');

        $expected = [
            'resourceType' => 'Bundle',
            'type' => 'collection',
        ];

        $expectedJSON = array_merge([
            // Type & id are required to build URLs, which are required by Bundles.
            'resourceType' => $resource,
            'id' => $this->getRecordFHIRId($pid, $recordId)
        ], $expectedJSON);

        $expected['entry'] = [
            [
                'fullUrl' => $this->getResourceUrl($expectedJSON),
                'resource' => $expectedJSON
            ]
        ];
        
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

        // ContactPoints (they have special handling)
        $homeEmailPath = 'telecom/home/email/value';
        $this->assert(
            [
                $fieldName => [
                    'element' => $homeEmailPath,
                    'value' => 'a@b.com'
                ],
                $fieldName2 => [
                    'element' => 'telecom/work/email/value',
                    'value' => 'c@d.com'
                ]
            ],
            [
                'telecom' => [
                    [
                        'use' => 'home',
                        'system' => 'email',
                        'value' => 'a@b.com',
                    ],
                    [
                        'use' => 'work',
                        'system' => 'email',
                        'value' => 'c@d.com',
                    ]
                ]
            ]
        );

        $error = '';
        try{
            $this->setFHIRMapping($fieldName2, "Patient/$homeEmailPath");
            $assert($homeEmailPath, 1, []);
        }
        catch(\Exception $e){
            $error = $e->getMessage();
        }

        $this->assertStringContainsString('currently mapped to multiple fields', $error);
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

    function testGetMappedFieldsAsBundle_elementsMappedTwice(){
        $this->assert(
            [
                $this->getFieldName() => [
                    'element' => 'name/given',
                    'value' => 'Billy'
                ],
                $this->getFieldName2() => [
                    'element' => 'name/given',
                    'value' => 'John'
                ]
            ],
            [
                'name' => [
                    [
                        'given' => [
                            'Billy',
                            'John'
                        ]
                    ]
                ]
            ]
        );

        $this->expectExceptionMessage('mapped to multiple fields');
        $this->assert(
            [
                $this->getFieldName() => [
                    'element' => 'name/family',
                    'value' => 'One'
                ],
                $this->getFieldName2() => [
                    'element' => 'name/family',
                    'value' => 'Two'
                ]
            ],
            []
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
                ]
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
}