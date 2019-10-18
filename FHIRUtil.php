<?php
require_once __DIR__ . '/vendor/autoload.php';

use HL7\FHIR\R4\PHPFHIRResponseParser;
use HL7\FHIR\R4\FHIRResource;
use HL7\FHIR\R4\FHIRElement\FHIRString;
use HL7\FHIR\R4\FHIRResource\FHIRBundle;
use HL7\FHIR\R4\FHIRElement\FHIRHumanName;
use HL7\FHIR\R4\FHIRElement\FHIRReference;
use HL7\FHIR\R4\FHIRElement\FHIRContactPoint;
use HL7\FHIR\R4\FHIRElement\FHIRCodeableConcept;
use HL7\FHIR\R4\FHIRElement\FHIRContactPointSystem;
use HL7\FHIR\R4\FHIRElement\FHIRResearchStudyStatus;
use HL7\FHIR\R4\FHIRResource\FHIRDomainResource\FHIRComposition;
use HL7\FHIR\R4\FHIRResource\FHIRDomainResource\FHIROrganization;
use HL7\FHIR\R4\FHIRResource\FHIRDomainResource\FHIRPractitioner;
use HL7\FHIR\R4\FHIRResource\FHIRDomainResource\FHIRResearchStudy;
use HL7\FHIR\R4\FHIRElement\FHIRBackboneElement\FHIRBundle\FHIRBundleEntry;
use HL7\FHIR\R4\FHIRElement\FHIRBackboneElement\FHIROrganization\FHIROrganizationContact;

class FHIRUtil
{
    function parse($data) {
        $parser = new PHPFHIRResponseParser();
        return $parser->parse($data);
    }
    
    function jsonSerialize($FHIRObject){
        $a = json_decode(json_encode($FHIRObject->jsonSerialize()), true);
        
        $handle = function(&$a) use (&$handle){
            foreach($a as $key=>&$value){
                if($key[0] === '_'){
                    unset($a[$key]);
                    continue;
                }

                if(is_array($value)){
                    $handle($value);
                }
            }
        };

        $handle($a);

        return json_encode($a, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    }

    function xmlSerialize($FHIRObject){
        $dom = dom_import_simplexml($FHIRObject->xmlSerialize())->ownerDocument;
        $dom->formatOutput = true;
        return $dom->saveXML();
    }

    function buildBundle(){
        $bundle = new FHIRBundle;

        $getReference = function ($o) use ($bundle){
            $id = $o->getId();
            if(empty($id)){
                throw new Exception('A reference cannot be created for an object without an id!');
            }
        
            $existsInBundle = false;
            foreach($bundle->getEntry() as $entry){
                if($entry->getResource() === $o){
                    $existsInBundle = true;
                }
            }
            
            if(!$existsInBundle){
                throw new Exception("A reference cannot be created for an object that hasn't been added to the bundle!");
            }
        
            return new FHIRReference([
                'reference' => $o->getFHIRTypeName() . "/$id"
            ]);
        };

        
        $addToBundle = function ($o) use ($bundle){
            $bundle->addEntry(new FHIRBundleEntry([
                'resource' => $o
            ]));

            return $o;
        };

        
        $sponsor = $addToBundle(new FHIROrganization([
            'id' => '123',
            'name' => 'Mongoloid University',
            'contact' => [
                new FHIROrganizationContact([
                    'name' => new FHIRHumanName([
                        'given' => 'Joe',
                        'family' => 'Bloe'
                    ]),
                    'telecom' => new FHIRContactPoint([
                        'system' => new FHIRContactPointSystem([
                            'value' => 'email'
                        ]),
                        'value' => 'joe.bloe@shmoe.com'
                    ])
                ])
            ]
        ]));
        
        $pi = $addToBundle(new FHIRPractitioner([
            'id' => '123',
            'name' => new FHIRHumanName([
                'given' => 'John',
                'family' => 'Bon'
            ]),
            'telecom' => new FHIRContactPoint([
                'system' => new FHIRContactPointSystem([
                    'value' => 'email'
                ]),
                'value' => 'John.Bon@jovi.com'
            ])
        ]));
        
        $study = $addToBundle(new FHIRResearchStudy([
            'id' => '123',
            'title' => 'Some study',
            'status' => new FHIRResearchStudyStatus([
                'value' => 'TBD' // TODO
            ]),
            'principalInvestigator' => $getReference($pi),
            'sponsor' => $getReference($sponsor),
        ]));
        
        $compositionAuthor = $addToBundle(new FHIRPractitioner([
            'id' => '123',
            'name' => new FHIRHumanName([
                'given' => 'Joe',
                'family' => 'Bloe'
            ]),
            'telecom' => new FHIRContactPoint([
                'system' => new FHIRContactPointSystem([
                    'value' => 'email'
                ]),
                'value' => 'joe.bloe@shmoe.com'
            ])
        ]));
        
        $composition = $addToBundle(new FHIRComposition([
            'id' => '123',
            'type' => new FHIRCodeableConcept([
                'text' => 'Determination Letter'
            ]),
            'author' => $getReference($compositionAuthor),
            'subject' => $getReference($study)
        ]));

        return $bundle;
    }

    function questionnaireToDataDictionary($questionnaire){
        $q = json_decode(file_get_contents($questionnaire));
        $forms = [];

        if($q->resourceType !== 'Questionnaire'){
            throw new Exception("Unexpected resource type : " . $q->resourceType);
        }

        $createObject = function ($item){
            $object = new stdClass();
            @$object->type = $item->type;
            @$object->text = $item->text;

            return $object;
        };

        $handleItems = function ($group) use (&$handleItems, $createObject, &$forms){
            $groupId = @$group->linkId;
            $fields = [];

            foreach($group->item as $item){
                $id = $item->linkId;
                if($item->type === 'group'){
                    if($groupId && strpos($id, $groupId) !== 0){
                        throw new Exception("The item ID ($id) does not start with it's parent group ID ($groupId)!  If this is expected then we'll need a different way to track parent/child relationships.");
                    }
                    
                    $handleItems($item);
                }
                else{
                    if(isset($fields[$id])){
                        throw new Exception("The following linkId is defined twice: $id");
                    }
                    else if(@$item->repeats){
                        throw new Exception("The following field repeats, which is only supportted for groups currently: $id");
                    }
                    else if($item->text !== $item->code[0]->display){
                        throw new Exception("Text & display differ: '{$item->text}' vs. '{$item->code[0]->display}'");
                    }
                }

                $fields[$id] = $createObject($item);
            }

            $form = $createObject($group);
            $form->id = $groupId;
            $form->fields = $fields;

            if(@$group->repeats){
                $form->repeats = true;
            }
            
            if($groupId){
                $forms[$groupId] = $form;
            }
            else if(empty($fields)){
                throw new Exception("Top level fields are not supported: " . json_encode($fields, JSON_PRETTY_PRINT));
            }
        };

        $handleItems($q);

        return json_encode($forms, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    }
}