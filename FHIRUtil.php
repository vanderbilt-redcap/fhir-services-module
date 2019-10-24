<?php
require_once __DIR__ . '/vendor/autoload.php';

use DCarbone\PHPFHIRGenerated\R4\PHPFHIRResponseParser;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRString;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRBundle;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRHumanName;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRReference;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRContactPoint;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRCodeableConcept;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRContactPointSystem;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRResearchStudyStatus;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRComposition;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIROrganization;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRPractitioner;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRResearchStudy;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRBundle\FHIRBundleEntry;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIROrganization\FHIROrganizationContact;

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
                'reference' => $o->_getFHIRTypeName() . "/$id"
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
        $q = FHIRUtil::parse(file_get_contents($questionnaire));

        if($q->_getFHIRTypeName() !== 'Questionnaire'){
            throw new Exception("Unexpected resource type : " . $q->resourceType);
        }
        
        $out = fopen('php://memory', 'r+');
        fputcsv($out, ["Variable / Field Name","Form Name","Section Header","Field Type","Field Label","Choices, Calculations, OR Slider Labels","Field Note","Text Validation Type OR Show Slider Number","Text Validation Min","Text Validation Max","Identifier?","Branching Logic (Show field only if...)","Required Field?","Custom Alignment","Question Number (surveys only)","Matrix Group Name","Matrix Ranking?","Field Annotation"]);
       
        self::walkQuestionnaire($q, function($parent, $item) use ($out){
            $instrumentName = self::getInstrumentName($parent);

            // We'll use the instrument name function for fields too until UAMS comes up with better field names.
            $fieldName = $instrumentName . '_' . self::getInstrumentName($item);

            fputcsv($out, [$fieldName, $instrumentName, '', self::getType($item), self::getText($item)]);
        });

        rewind($out);

        return stream_get_contents($out);
    }

    function walkQuestionnaire($group, $fieldAction){
        $handleItems = function ($group) use (&$handleItems, &$out, &$fieldAction){
            $groupId = self::getLinkId($group);

            foreach($group->getItem() as $item){
                $id = $item->getLinkId()->getValue()->getValue();
                if($item->getType()->getValue()->getValue()->getValue() === 'group'){
                    if($groupId && strpos($id, $groupId) !== 0){
                        throw new Exception("The item ID ($id) does not start with it's parent group ID ($groupId)!  If this is expected then we'll need a different way to track parent/child relationships.");
                    }
                    
                    $handleItems($item);
                }
                else{
                    if(self::isRepeating($item)){
                        throw new Exception("The following field repeats, which is only supportted for groups currently: $id");
                    }
                    else if($item->getText()->__toString() !== $item->getCode()[0]->getDisplay()->__toString()){
                        throw new Exception("Text & display differ: '{$item->getText()}' vs. '{$item->getCode()[0]->getDisplay()}'");
                    }

                    $fieldAction($group, $item);
                } 
            }
        };

        $handleItems($group);
    }

    function isRepeating($item){
        if($item->_getFHIRTypeName() !== 'Questionnaire.Item'){
            return false;
        }

        $repeats = $item->getRepeats();
        return $repeats && $repeats->getValue()->getValue();
    }

    function getLinkId($item){
        if($item->_getFHIRTypeName() !== 'Questionnaire.Item'){
            return null;
        }

        return $item->getLinkId()->getValue()->getValue();
    }

    function getType($item){
        if($item->_getFHIRTypeName() === 'Questionnaire.Item'){
            $type = $item->getType();
            if($type){
                $type = $type->getValue()->getValue()->getValue();
                if($type === 'string'){
                    $type = 'text';
                }

                return $type;
            }
        }
    }

    function getText($item){
        $text = $item->getText();
        if($text){
            return $text->getValue()->getValue();
        }
    }

    function getInstrumentName($group){
        $name = strtolower(self::getText($group));
        $name = str_replace(' ', '_', $name);
        $name = str_replace('(', '', $name);
        $name = str_replace(')', '', $name);
        $name = str_replace(':', '', $name);

        return $name;
    }
}