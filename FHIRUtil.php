<?php
require_once __DIR__ . '/vendor/autoload.php';

use DCarbone\PHPFHIRGenerated\R4\FHIRResource;
use DCarbone\PHPFHIRGenerated\R4\PHPFHIRResponseParser;
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
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRQuestionnaireResponse;
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

    private function getPidFromSqlField($pid, $fieldName){
        $pid = db_escape($pid);
        $fieldName = db_escape($fieldName);

        $sql = "
            select element_enum
            from redcap_metadata
            where project_id = $pid
            and field_name = '$fieldName'
        ";

        $result = db_query($sql);

        $row = $result->fetch_assoc();
        if($result->fetch_assoc() !== null){
            throw new Exception("Multiple fields found!");
        }

        $sql = $row['element_enum'];
        preg_match("/project_id \= ([0-9]+)/", $sql, $matches);

        return $matches[1];
    }

    function getData($pid, $record){
        return json_decode(REDCap::getData($pid, 'json', $record), true);
    }

    function buildBundle($compositionsPid, $compositionId){
        $practitionersPid = self::getPidFromSqlField($compositionsPid, 'author_id');
        $studiesPid = self::getPidFromSqlField($compositionsPid, 'subject_id');
        $organizationsPid = self::getPidFromSqlField($studiesPid, 'sponsor_id');
    
        $compositionData = self::getData($compositionsPid, $record)[0];
        $authorData = self::getData($practitionersPid, $compositionData['author_id'])[0]; 
        $studyData = self::getData($studiesPid, $compositionData['study_id'])[0];
        $piData = self::getData($practitionersPid, $studyData['principal_investigator_id'])[0];
        
        $sponsorInstances = self::getData($organizationsPid, $studyData['sponsor_id']);
        $sponsorContacts = [];
        foreach($sponsorInstances as $instance){
            $instrument = $instance['redcap_repeat_instrument'];
            if(empty($instrument)){
                $sponsorData = $instance;
            }
            else if($instrument === 'contacts'){
                $sponsorContacts[] = $instance;
            }
            else{
                throw new Exception("Unsupported repeating instrument: $instrument");
            }
        }
        
        $bundle = new FHIRBundle;

        $getReference = function ($o) use ($bundle){
            $id = $o->getId();
            if(empty($id)){
                throw new Exception('A reference cannot be created for an object without an id: ' . self::jsonSerialize($o));
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
            'id' => $sponsorData['organization_id'],
            'name' => $sponsorData['organization_name']
        ]));

        foreach($sponsorContacts as $contact){
            $sponsor->addContact(new FHIROrganizationContact([
                'name' => new FHIRHumanName([
                    'given' => $contact['contact_first_name'],
                    'family' => $contact['contact_last_name']
                ]),
                'telecom' => new FHIRContactPoint([
                    'system' => new FHIRContactPointSystem([
                        'value' => 'email'
                    ]),
                    'value' => $contact['contact_email']
                ])
            ]));
        }
        
        $pi = $addToBundle(new FHIRPractitioner([
            'id' => $piData['practitioner_id'],
            'name' => new FHIRHumanName([
                'given' => $piData['first_name'],
                'family' => $piData['last_name']
            ]),
            'telecom' => new FHIRContactPoint([
                'system' => new FHIRContactPointSystem([
                    'value' => 'email'
                ]),
                'value' => $piData['email']
            ])
        ]));
        
        $study = $addToBundle(new FHIRResearchStudy([
            'id' => $studyData['study_id'],
            'title' => $studyData['title'],
            'status' => new FHIRResearchStudyStatus([
                'value' => $studyData['status']
            ]),
            'principalInvestigator' => $getReference($pi),
            'sponsor' => $getReference($sponsor),
        ]));
        
        $compositionAuthor = $addToBundle(new FHIRPractitioner([
            'id' => $authorData['practitioner_id'],
            'name' => new FHIRHumanName([
                'given' => $authorData['first_name'],
                'family' => $authorData['last_name']
            ]),
            'telecom' => new FHIRContactPoint([
                'system' => new FHIRContactPointSystem([
                    'value' => 'email'
                ]),
                'value' => $authorData['email']
            ])
        ]));
        
        $addToBundle(new FHIRComposition([
            'id' => $compositionData['composition_id'],
            'type' => new FHIRCodeableConcept([
                'text' => $compositionData['type']
            ]),
            'author' => $getReference($compositionAuthor),
            'subject' => $getReference($study)
        ]));

        return $bundle;
    }

    function getQuestionnaireResponse($projectId, $responseId){
        // $data = REDCap::getData($projectId, 'json', $responseId)[0];
        return new FHIRQuestionnaireResponse;
    }

    function questionnaireToDataDictionary($questionnaire){
        $q = FHIRUtil::parse(file_get_contents($questionnaire));

        if($q->_getFHIRTypeName() !== 'Questionnaire'){
            throw new Exception("Unexpected resource type : " . $q->resourceType);
        }
        
        $out = fopen('php://memory', 'r+');
        fputcsv($out, ["Variable / Field Name","Form Name","Section Header","Field Type","Field Label","Choices, Calculations, OR Slider Labels","Field Note","Text Validation Type OR Show Slider Number","Text Validation Min","Text Validation Max","Identifier?","Branching Logic (Show field only if...)","Required Field?","Custom Alignment","Question Number (surveys only)","Matrix Group Name","Matrix Ranking?","Field Annotation"]);
        
        $idRowAdded = false;
        self::walkQuestionnaire($q, function($parent, $item) use ($out, &$idRowAdded){
            $fieldName = self::getFieldName($parent, $item);
            $instrumentName = self::getInstrumentName($parent);

            if(!$idRowAdded){
                fputcsv($out, ['response_id', $instrumentName, '', 'text', 'Response ID']);
                $idRowAdded = true;
            }

            fputcsv($out, [$fieldName, $instrumentName, '', self::getType($item), self::getText($item)]);
        });

        rewind($out);

        return stream_get_contents($out);
    }

    function getFieldName($parent, $item){
        $n = $item->getLinkId()->getValue()->getValue();
        $n = strtolower($n);
        $n = ltrim($n, '/');
        $n = str_replace('/', '_', $n);
        $n = str_replace('.', '_', $n);
        $n = str_replace('[', '', $n);
        $n = str_replace(']', '', $n);

        return $n;
    }

    function questionnaireResponseToREDCapExport($path){
        $o = FHIRUtil::parse(file_get_contents($path));

        $data = ['response_id' => ''];

        $handleObject = function($parent) use (&$handleObject, &$data){
            foreach($parent->getItem() as $item){
                $answers = $item->getAnswer();
                if(empty($answers)){
                    $handleObject($item);
                }
                else{
                    foreach($answers as $answer){
                        $data[self::getFieldName($parent, $item)] = self::getAnswerValue($item, $answer);
                    }
                }
            }
        };

        $handleObject($o);

        $out = fopen('php://memory', 'r+');

        fputcsv($out, array_keys($data));
        fputcsv($out, $data);

        rewind($out);

        return stream_get_contents($out);
    }

    function getAnswerValue($item, $answer){
        $v = $answer->getValueString()->getValue()->__toString();

        if(self::getText($item) === 'Last Updated at:'){
            $v = DateTime::createFromFormat('F j, Y \a\t g:i A e', $v)->format('Y-m-d H:i');
        }

        return $v;
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