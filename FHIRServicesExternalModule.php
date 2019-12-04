<?php namespace Vanderbilt\FHIRServicesExternalModule;

require_once __DIR__ . '/vendor/autoload.php';

use REDCap;
use DateTime;
use MetaData;
use Exception;

use DCarbone\PHPFHIRGenerated\R4\FHIRResource;
use DCarbone\PHPFHIRGenerated\R4\PHPFHIRResponseParser;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRCoding;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRString;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRBundle;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRHumanName;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRReference;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBundleType;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRIdentifier;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRContactPoint;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRCodeableConcept;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRContactPointSystem;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRResearchStudyStatus;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRComposition;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIROrganization;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRPractitioner;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRResearchStudy;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRPractitionerRole;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRQuestionnaireResponse;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRBundle\FHIRBundleEntry;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRComposition\FHIRCompositionSection;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIROrganization\FHIROrganizationContact;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRQuestionnaireResponse\FHIRQuestionnaireResponseItem;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRQuestionnaireResponse\FHIRQuestionnaireResponseAnswer;

const RESOURCE_RECEIVED = 'Resource Received';

class FHIRServicesExternalModule extends \ExternalModules\AbstractExternalModule{
    function redcap_every_page_top(){
        if(strpos($_SERVER['REQUEST_URI'], APP_PATH_WEBROOT . 'DataEntry/record_home.php') === 0){
            $projectId = $this->getProjectId();
            $recordId = $_GET['id'];
            $urlPrefix = $this->getUrl('service.php', true);
            $urlPrefix = str_replace("&pid=$projectId", '', $urlPrefix); 

            $projectType = $this->getProjectType();
            $resourceName = null;
            if($projectType === 'composition'){
                $resourceName ='Bundle';
            }
            else if($projectType === 'questionnaire'){
                $resourceName = 'QuestionnaireResponse';
            }
            else{
                return;
            }

            ?>
            <div id="fhir-services-send-record" class="modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-body">
                            <p>Sending record to remote FHIR server...</p>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                (function(){
                    var waitForElement = function(selector, callback) {
                        var element = $(selector)
                        if (element.length) {
                            callback(element);
                        } else {
                            setTimeout(function() {
                                waitForElement(selector, callback);
                            }, 100);
                        }
                    }

                    var sendRecord = function(testing){
                        var url = <?=json_encode($this->getUrl('send-record.php') . "&id=" . $_GET['id'])?>;

                        if(testing){
                            url = url + '&test'
                            window.open(url)
                            return
                        }
                       
                        var dialog = $('#fhir-services-send-record')
                        dialog.modal('show')

                        $.post(url).always(function(response){
                            if(response.status === 'success'){
                                alert('The data was successfully sent to the remote FHIR server.')
                                console.log('Remote Response: ' + response['remote-response'])
                            }
                            else{
                                alert('An error ocurred.  See the browser log for details.')
                                console.log(response)
                            }

                            dialog.modal('hide')
                        })
                    }

                    waitForElement('#recordActionDropdownDiv', function(dropdown){                       
                        var lastPdfOption = $(dropdown.find('a[href*=\\/PDF\\/]').toArray().reverse()[0]).parent()
                        
                        var addOption = function(text, iconName, action){
                            var newOption = lastPdfOption.clone()

                            var icon = newOption.find('i.fas')[0]
                            icon.className = 'fas fa-' + iconName
                            icon.nextSibling.textContent = ' ' + text             

                            var a = newOption.find('a')
                            a.removeAttr('target')
                            
                            newOption.click(function(e){
                                e.preventDefault()
                                dropdown.hide()
                                action()
                            })              
                            
                            lastPdfOption.after(newOption)
                            lastPdfOption = newOption
                        }
                        
                        var resourceName = <?=json_encode($resourceName)?>;
                        var openAction
                        if(resourceName === 'Bundle'){
                            openAction = function(){
                                sendRecord(true)
                            }
                        }
                        else{
                            openAction = function(){
                                window.open(<?=json_encode("$urlPrefix&fhir-url=/$resourceName/$projectId-$recordId")?>)
                            }
                        }

                        addOption('Open FHIR ' + resourceName, 'file', openAction)
                    
                        addOption('Send FHIR ' + resourceName + ' to remote FHIR server', 'file-export', function(){
                            sendRecord(false)
                        })
                    })
                })()
            </script>
            <?php
        }
    }

    function onDataDictionaryUploadPage(){
        ?>
        <div id="fhir-upload-container" class="round" style="background-color:#EFF6E8;max-width:700px;margin:20px 0;padding:15px 25px;border:1px solid #A5CC7A;">
        </div>
        <script>
            $(function(){
                var csvSection = $('#uploadmain').parent().parent()
                var fhirSection = csvSection.clone()
                csvSection.after(fhirSection)
            })
        </script>
        <?php
    }

    function redcap_module_link_check_display($project_id, $link){
        if(
            strpos($link['url'], 'questionnaire-options') !== false
            &&
            $this->getProjectType() !== 'questionnaire'
        ){
            return false;
        }
        
        return $link;
    }

    function getProjectType($projectId = null){
        return $this->getProjectSetting('project-type', $projectId);
    }

    // This method was mostly copied from data_dictionary_upload.php
    function replaceDataDictionaryWithQuestionnaire($file){
        $dictionaryPath = tempnam(sys_get_temp_dir(), 'fhir-questionnaire-data-dictionary-');
        
        try{
            list($csv, $repeatingFormNames) = $this->questionnaireToDataDictionary($file['tmp_name']);
            file_put_contents($dictionaryPath, $csv);

            require_once APP_PATH_DOCROOT . '/Design/functions.php';
            $dictionary_array = excel_to_array($dictionaryPath);
        }
        finally{
            unlink($dictionaryPath);
        }

        list ($errors_array, $warnings_array, $dictionary_array) = MetaData::error_checking($dictionary_array);

        $checkForErrors = function($errors, $type, $runOnError = null){
            $errors = array_filter($errors, function($error){
                global $lang;

                if(strpos($error, $lang['database_mods_30']) === 0){
                    return false;
                }

                return true;
            });

            if(empty($errors)){
                return false;
            }

            if($runOnError){
                $runOnError();
            }

            ?>
            <p>Uploading the questionnaire failed with the following <?=$type?>:</p>
            <pre><?=json_encode($errors)?></pre>
            <br>
            <?php
            
            throw new Exception("Uploading the questionnaire failed with the previously printed errors.");
        };

        $checkForErrors($errors_array, 'errors');
        $checkForErrors($warnings_array, 'warnings');

        // Set up all actions as a transaction to ensure everything is done here
        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");
        
        // Create a data dictionary snapshot of the *current* metadata and store the file in the edocs table
        MetaData::createDataDictionarySnapshot();

        // Save data dictionary in metadata table
        $sql_errors = MetaData::save_metadata($dictionary_array);

        // Display any failed queries to Super Users, but only give minimal info of error to regular users
        $checkForErrors($sql_errors, 'errors', function(){
            // ERRORS OCCURRED, so undo any changes made
            db_query("ROLLBACK");
            // Set back to previous value
            db_query("SET AUTOCOMMIT=1");

            return;
        });

        // COMMIT CHANGES
        db_query("COMMIT");
        // Set back to previous value
        db_query("SET AUTOCOMMIT=1");

        $eventId = $this->getEventId();
        
        $this->query('delete from redcap_events_repeat where event_id = ?', $eventId);
        
        foreach($repeatingFormNames as $formName){
            $this->query('insert into redcap_events_repeat (event_id, form_name) values (?, ?)', [$eventId, $formName]);
        }

        $edocId = \Files::uploadFile($file, $this->getProjectId());
        $this->setProjectSetting('questionnaire', $edocId);
    }

    function getEventId($projectId = null){
        if(!$projectId){
            $projectId = $this->getProjectId();
		}
		
		$sql = '
			select event_id
			from redcap_events_arms a
			join redcap_events_metadata m
				on m.arm_id = a.arm_id
			where project_id = ?
        ';
        
		$result = $this->query($sql, $projectId);
		$row = $result->fetch_assoc();

		if($result->fetch_assoc()){
			throw new Exception("Multiple event IDs found from project $projectId");
		}

		return $row['event_id'];
    }

    function getQuestionnaireEDoc($projectId = null){
        $edocId = $this->getProjectSetting('questionnaire', $projectId);
        if(!$edocId){
            return null;
        }

        $edocId = db_escape($edocId);
        $result = $this->query("select * from redcap_edocs_metadata where doc_id = $edocId");
        return $result->fetch_assoc();
    }

    function parse($data) {
        $parser = new PHPFHIRResponseParser();
        return $parser->parse($data);
    }
    
    function jsonSerialize($FHIRObject){
        if(empty($FHIRObject)){
            throw new Exception('A valid FHIR object must be specified.');
        }

        $a = $FHIRObject->jsonSerialize();
        $a = json_decode(json_encode($a), true);
        
        $handle = function(&$a) use (&$handle){
            $fixContact = function(&$contact){
                foreach($contact['telecom'] as &$telecom){
                    $telecom['system'] = $telecom['system']['value'];
                }
            };

            $type = $a['resourceType'];
            if($type === 'Organization'){
                $contacts = $a['contact'];
                if(!empty($contacts)){
                    foreach($a['contact'] as &$contact){
                        $fixContact($contact);
                    }
                }
            }
            else if($type === 'Practitioner'){
                $fixContact($a);
            }
            else if($type === 'Bundle'){
                $a['type'] = $a['type']['value'];
            }
            else if($type === 'Composition'){
                $a['confidentiality'] = $a['confidentiality']['value'];
                $a['status'] = $a['status']['value'];

                foreach($a['section'] as &$section){
                    $section['text']['status'] = $section['text']['status']['value'];
                }
            }
            else if(in_array($type, ['QuestionnaireResponse', 'ResearchStudy'])){
                $a['status'] = $a['status']['value'];
            }

            foreach($a as $key=>&$value){
                if($key[0] === '_'){
                    // TODO - Contribute this change back.
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
        if(empty($pid)){
            throw new Exception('A project id must be specified.');
        }

        $pid = db_escape($pid);
        $fieldName = db_escape($fieldName);

        $sql = "
            select element_enum
            from redcap_metadata
            where project_id = $pid
            and field_name = '$fieldName'
        ";

        $result = $this->query($sql);

        $row = $result->fetch_assoc();
        if($row === null){
            throw new Exception("Could not find the field named '$fieldName' for project $pid.");
        }

        if($result->fetch_assoc() !== null){
            throw new Exception("Multiple fields found!");
        }

        $sql = $row['element_enum'];
        preg_match("/project_id \= ([0-9]+)/", $sql, $matches);

        return $matches[1];
    }

    function getData($pid, $record){
        if(empty($record)){
            throw new Exception('A record ID is required.');
        }

        return json_decode(REDCap::getData($pid, 'json', $record), true);
    }

    function getFHIRUrl(){
        return $_GET['fhir-url'];
    }

    function getFHIRUrlParts(){
        $fhirUrl = $this->getFHIRUrl();

        if(empty($fhirUrl)){
            throw new Exception("You must specify a 'fhir-url' parameter.");
        }

        return explode('/', $fhirUrl);
    }

    function getProjectAndRecordIdsFromFHIRUrl(){
        $urlParts = $this->getFHIRUrlParts();
        $resourceId = $urlParts[2];
        $idParts = explode('-', $resourceId);
        $projectId = $idParts[0];
        $recordId = $idParts[1];

        if(
            empty($projectId)
            ||
            !ctype_digit($projectId)
            ||
            empty($recordId)
        ){
            $sendErrorResponse("The resource ID specified is not valid: $resourceId");
        }

        return [$projectId, $recordId]; 
    }

    function addIdentifier($resource, $projectId, $recordId){
        if($resource->getId()){
            throw new Exception('The ID is already set!');
        }

        $resourceId = "$projectId-$recordId";

        $resource->setId($resourceId);

        $identifier = $resource->getIdentifier();
        if($identifier){
            // We serialize the resource instead of the identifier because the latter could be an array (which wouldn't directly serialize).
            throw new Exception("Cannot add an identifier because one is already set: " . $this->jsonSerialize($resource));
        }
        
        $identifier = new FHIRIdentifier([
            'system' => APP_PATH_WEBROOT_FULL,
            'value' => $resourceId
        ]);

        $methodName = 'addIdentifier';
        if(!method_exists($resource, $methodName)){
            $methodName = 'setIdentifier';
        }

        $resource->{$methodName}($identifier);

        return $resource;
    }

    function formatTimestamp($timestamp){
        return date('Y-m-d\TH:i:sP', $timestamp);
    }

    function buildBundle($compositionsPid, $compositionId){
        $practitionerRolesPid = $this->getPidFromSqlField($compositionsPid, 'author_id');
        $practitionersPid = $this->getPidFromSqlField($practitionerRolesPid, 'practitioner_id');
        $studiesPid = $this->getPidFromSqlField($compositionsPid, 'subject_id');
        $organizationsPid = $this->getPidFromSqlField($studiesPid, 'sponsor_id');
    
        $compositionData = $this->getData($compositionsPid, $compositionId)[0];
        
        $authorId = $compositionData['author_id'];
        $authorRoleData = $this->getData($practitionerRolesPid, $authorId)[0];
        $authorPractitionerId = $authorRoleData['practitioner_id'];

        $authorPractitionerData = $this->getData($practitionersPid, $authorPractitionerId)[0];
        $authorOrganizationId = $authorRoleData['organization_id'];
        $authorOrganizationData = $this->getData($organizationsPid, $authorOrganizationId);

        // TODO - Add support for contacts
        if(count($authorOrganizationData) > 1){
            throw new Exception("Instances are not currently supported for the author organization");
        }
        else{
            $authorOrganizationData = $authorOrganizationData[0];
        }
        
        $studyId = $compositionData['subject_id'];
        $studyData = $this->getData($studiesPid, $studyId)[0];

        $piId = $studyData['principal_investigator_id'];
        $piData = $this->getData($practitionersPid, $piId)[0];
        
        $sponsorId = $studyData['sponsor_id'];
        $sponsorInstances = $this->getData($organizationsPid, $sponsorId);
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
        
        $bundle = new FHIRBundle([
            'timestamp' => $this->formatTimestamp(time()),
            'type' => [
                'value' => 'document'
            ]
        ]);

        $this->addIdentifier($bundle, $compositionsPid, $compositionId);

        $getReferenceString = function($resource){
            $id = $resource->getId();
            if(empty($id)){
                throw new Exception('A reference cannot be created for an object without an id: ' . $this->jsonSerialize($resource));
            }

            return $resource->_getFHIRTypeName() . "/$id";
        };

        $getResourceFromReference = function($reference) use ($bundle, $getReferenceString){
            $expectedReferenceString = $this->getValue($reference->getReference());

            foreach($bundle->getEntry() as $entry){
                $resource = $entry->getResource();

                if(empty($resource->getId())){
                    // A reference string can't be created for items without an id.
                    continue;
                }
                
                if($expectedReferenceString === $getReferenceString($resource)){
                    return $resource;
                }
            }

            return null;
        };

        $getReference = function ($o) use ($bundle, $getReferenceString, $getResourceFromReference){
            if(!$o){
                throw new Exception("A FHIR resource must be specified.");
            }

            $reference = new FHIRReference([
                'reference' => $getReferenceString($o)
            ]);
                    
            if(!$getResourceFromReference($reference)){
                throw new Exception("A reference cannot be created for an object that hasn't been added to the bundle: " . $this->jsonSerialize($o));
            }
        
            return $reference;
        };

        $getCompositionSection = function($id, $params = []) use ($getReference, $getResourceFromReference){
            extract($params);
    
            ob_start();
            require __DIR__ . "/templates/$id.php";
            $html = ob_get_clean();
    
            $section = new FHIRCompositionSection([
                'id' => $id,
                'text' => [
                    'status' => 'generated',
                    'div' => $html
                ]
            ]);
    
            foreach($params as $param){
                $section->addEntry($getReference($param));
            }
    
            return $section;
        };

        $addToBundle = function ($o, $projectId = null, $recordId = null) use ($bundle){
            if($projectId && $recordId){
                $this->addIdentifier($o, $projectId, $recordId);
            }

            $bundle->addEntry(new FHIRBundleEntry([
                'resource' => $o
            ]));

            return $o;
        };

        $composition = $addToBundle(new FHIRComposition([
            'status' => 'preliminary',
            'date' => $this->formatTimestamp(time()), // TODO - This should pull the last edit time from the log instead.
            'title' => $compositionData['type'],
            'confidentiality' => 'L', // TODO - Where should this come from?
            'type' => [
                'text' => $compositionData['type']
            ]
        ]));

        $sponsor = $addToBundle($this->getOrganizationFromRecord($sponsorData), $organizationsPid, $sponsorId);
        
        foreach($sponsorContacts as $contact){
            $sponsor->addContact(new FHIROrganizationContact([
                'name' => [
                    'given' => [
                        $contact['contact_first_name']
                    ],
                    'family' => $contact['contact_last_name']
                ],
                'telecom' => [
                    [
                        'system' => [
                            'value' => 'email'
                        ],
                        'value' => $contact['contact_email']
                    ]
                ]
            ]));
        }
        
        $pi = $addToBundle(new FHIRPractitioner([
            'name' => [
                [
                    'given' => $piData['first_name'],
                    'family' => $piData['last_name']
                ]
            ],
            'telecom' => [
                    [
                    'system' => [
                        'value' => 'email'
                    ],
                    'value' => $piData['email']
                ]
            ]
        ]), $practitionersPid, $piId);
        
        $study = $addToBundle(new FHIRResearchStudy([
            'title' => $studyData['title'],
            'status' => $studyData['status'],
            'principalInvestigator' => $getReference($pi),
            'sponsor' => $getReference($sponsor),
        ]), $studiesPid, $studyId);

        $study->addIdentifier(new FHIRIdentifier([
            'type' => [
                'coding' => [
                    [
                        'system' => 'http://ncimeta.nci.nih.gov',
                        'code' => 'C2985863',
                        'display' => 'Review Board Approval Number'
                    ]
                ]
            ],
            'value' => '123456789' // TODO - where will this value come from?
        ]));
        
        $authorOrganization = $addToBundle($this->getOrganizationFromRecord($authorOrganizationData), $organizationsPid, $authorOrganizationId);

        // TODO - Combine this and the other FHIRPractitioner above into a getPractitionerFromRecord() method
        $authorPractitioner = $addToBundle(new FHIRPractitioner([
            'name' => [
                [
                    'given' => $authorPractitionerData['first_name'],
                    'family' => $authorPractitionerData['last_name']
                ]
            ],
            'telecom' => [
                [
                    'system' => 'email',
                    'value' => $authorPractitionerData['email']
                ]
            ]
        ]), $practitionersPid, $authorPractitionerId);

        $authorPractitionerRole = $addToBundle(new FHIRPractitionerRole([
            'practitioner' => $getReference($authorPractitioner),
            'organization' => $getReference($authorOrganization),
            'code' => [
                [
                    'coding' => [
                        [
                            'display' => 'Site Investigator' // TODO - Where should this come from?
                        ]
                    ]
                ]
            ]
        ]), $practitionerRolesPid, $authorId);
       
        $composition->addAuthor($getReference($authorPractitionerRole));
        $composition->setSubject($getReference($study));
        $composition->addSection($getCompositionSection('title'));
        $composition->addSection($getCompositionSection('information', [
            'relyingOrg' => $authorOrganization,
            'study' => $study,
            'piRole' => $authorPractitionerRole
        ]));
        $composition->addSection($getCompositionSection('close', [
            'study' => $study
        ]));

        return $bundle;
    }

    function getOrganizationFromRecord($organization){
        return new FHIROrganization([
            'type' => [
                [
                    'coding' => [
                        [
                            'code' => 'prov' // TODO - Where should this come from?
                        ]
                    ]
                ]
            ],
            'name' => $organization['organization_name']
        ]);
    }

    function getQuestionnaireResponse(){
        list($projectId, $responseId) = $this->getProjectAndRecordIdsFromFHIRUrl();
        
        // $data = REDCap::getData($projectId, 'json', $responseId)[0];
        return new FHIRQuestionnaireResponse;
    }

    function saveResource($expectedType){
        $input = file_get_contents('php://input');
        $o = $this->parse($input);
        $type = $o->_getFHIRTypeName();
        if($type !== $expectedType){
            throw new Exception("Expected a $expectedType but found the following type instead: $type");
        }

        // TODO - Add checks for required data here?  For example, Binary resources required a "contentType".

        $logId = $this->log(RESOURCE_RECEIVED, [
            'type' => $type,
            'content' => $input
        ]);

        $o->setId("$logId");

        return $o;
    }

    function questionnaireToDataDictionary($questionnaire){
        $q = $this->parse(file_get_contents($questionnaire));

        $expectedResourceType = 'Questionnaire';
        $actualResourceType = $q->_getFHIRTypeName();
        if($actualResourceType !== $expectedResourceType){
            throw new Exception("Expected a resource type of '$expectedResourceType', but found '$actualResourceType' instead." . $q->resourceType);
        }
        
        $forms = [];
        $formNames = [];
        $repeatingFormNames = [];
        $this->walkQuestionnaire($q, function($parents, $item) use (&$forms, &$repeatingFormNames){
            $fieldName = $this->getFieldName($item);
            $parent = end($parents);

            $instrumentName = $this->getInstrumentName($parent, $item);
            $path = self::getQuestionnairePath($parents, $instrumentName);
            $form = &$forms[$path];
            if(!$form){
                if($formNames[$instrumentName]){
                   throw new Exception("Two forms exist with the following name: $instrumentName");
                }
                
                $formNames[$instrumentName] = true;

                $form = [
                    'fields' => [],
                    'formName' => $instrumentName
                ];

                $forms[$path] = &$form;

                if(self::isRepeating($parent)){
                    self::checkForNestedRepeatingGroups($parents);
                    $repeatingFormNames[] = $instrumentName;
                }                
            }

            $form['fields'][$fieldName] = [
                'type' => $this->getType($item),
                'label' => $this->getLinkId($item) . ' - ' . $this->getText($item),
                'choices' => $this->getREDCapChoices($item)
            ];
        });
        
        $out = fopen('php://memory', 'r+');
        $firstForm = reset($forms);
        fputcsv($out, ["Variable / Field Name","Form Name","Section Header","Field Type","Field Label","Choices, Calculations, OR Slider Labels","Field Note","Text Validation Type OR Show Slider Number","Text Validation Min","Text Validation Max","Identifier?","Branching Logic (Show field only if...)","Required Field?","Custom Alignment","Question Number (surveys only)","Matrix Group Name","Matrix Ranking?","Field Annotation"]);
        fputcsv($out, ['response_id', $firstForm['formName'], '', 'text', 'Response ID']);

        foreach($forms as $form){
            foreach($form['fields'] as $name=>$field){
                fputcsv($out, [$name, $form['formName'], '', $field['type'], $field['label'], $field['choices']]);
            }
        }

        rewind($out);

        $csv = stream_get_contents($out);

        //var_dump($csv);

        return [
            $csv,
            $repeatingFormNames,
        ];
    }

    private function getQuestionnairePath($parents, $formName){
        $parts = [];
        foreach($parents as $parent){
            $parts[] = self::getInstrumentName($parent);
        }

        $parts[] = $formName;

        return implode('/', $parts);
    }

    private function checkForNestedRepeatingGroups($groups){
        $repeatingCount = 0;
        foreach($groups as $group){
            if(self::isRepeating($group)){
                $repeatingCount++;
            }
        }

        if($repeatingCount > 1){
            throw new Exception("Only one level of nested repeating groups is supported currently, but $repeatingCount were found.");
        }
    }

    function getAnswers($item){
        $answers = [];
        foreach($item->getAnswerOption() as $option){
            $coding = $option->getValueCoding();
            $code = $this->getValue($coding->getCode());
            $display = $this->getValue($coding->getDisplay());
            
            $answers[$code] = $display;
        }

        return $answers;
    }

    function getREDCapChoices($item){
        if(empty($item->getAnswerOption())){
            return null;
        }

        $choices = [];
        foreach($this->getAnswers($item) as $code=>$display){
            $choices[] = "$code, $display";
        }

        return implode('|', $choices);
    }

    function getValue($o){
        if($o === null){
            return null;
        }

        while(method_exists($o, 'getValue')){
            $o = $o->getValue();
        }

        return $o;
    }

    function getFieldName($item){
        $n = $item->getLinkId()->getValue()->getValue();
        $n = strtolower($n);
        $n = ltrim($n, '/');
        $n = str_replace('-', '_', $n);
        $n = str_replace('/', '_', $n);
        $n = str_replace('.', '_', $n);
        $n = str_replace('[', '', $n);
        $n = str_replace(']', '', $n);

        if(ctype_digit($n[0])){
            // REDCap fields can't start with a number.
            $n = "q_$n";
        }

        return $n;
    }

    function questionnaireResponseToREDCapExport($path){
        $o = $this->parse(file_get_contents($path));
        $repeatingForms = array_fill_keys($this->getRepeatingForms(), true);

        $fieldNames = [];
        $data = [];
        $instanceCount = 0;

        $handleObject = function($parent) use (&$handleObject, &$fieldNames, &$data, &$instanceCount, $repeatingForms){
            foreach($parent->getItem() as $item){
                $answers = $item->getAnswer();
                if(empty($answers)){
                    $handleObject($item);
                }
                else{
                    foreach($answers as $answer){                        
                        $fieldName = $this->getFieldName($item);
                        $value = $this->getAnswerValue($item, $answer);
                        
                        $formName = $this->getInstrumentName($parent, $item);
                        $repeatInstrument = '';
                        if($repeatingForms[$formName]){
                            $repeatInstrument = $formName;
                        }
                        
                        $fieldNames[$fieldName] = true;
            
                        $fieldData = &$data[$repeatInstrument][$fieldName];
                        $fieldData[] = $value;

                        $instanceCount = max($instanceCount, count($fieldData));
                    }
                }
            }
        };

        $handleObject($o);

        $out = fopen('php://memory', 'r+');

        $fieldNames = array_keys($fieldNames);
        fputcsv($out, array_merge(
            [
                'response_id',
                'redcap_repeat_instrument',
                'redcap_repeat_instance'
            ],
            $fieldNames
        ));
        
        foreach($data as $repeatInstrument=>$instancesByFieldName){
            for($instance=1; $instance <= $instanceCount; $instance++){
                $row = [
                    'TBD', // Record ID (response_id)
                ];

                if(!$repeatInstrument){
                    $row[] = '';
                    $row[] = '';
                }
                else{
                    $row[] = $repeatInstrument;
                    $row[] = $instance;
                }

                $rowHasValues = false;
                foreach($fieldNames as $fieldName){
                    $value = @$instancesByFieldName[$fieldName][$instance-1];
                    $row[] = $value;

                    if($value !== null){
                        $rowHasValues = true;
                    }
                }

                if($rowHasValues){
                    fputcsv($out, $row);
                }

                if(!$repeatInstrument){
                    break; // No reason to iterate over the other instances
                }
            }            
        }

        rewind($out);

        $csv = stream_get_contents($out);
        // var_dump($csv);die();

        return $csv;
    }

    private function getRepeatingForms(){
        $result = $this->query('select * from redcap_events_repeat where event_id = ?', $this->getEventId());

        $forms = [];
        while($row = $result->fetch_assoc()){
            $forms[] = $row['form_name'];
        }

        return $forms;
    }

    function getAnswerValue($item, $answer){
        $v = $this->getValue($answer->getValueString());
        
        if($v === null){
            $v = $this->getValue($answer->getValueDateTime());

            if(!empty($v)){
                $v = (new DateTime($v))->format('Y-m-d H:i');
            }
        }

        if($v === null){
            $v = $this->getValue($answer->getValueInteger());
        }

        if($v === null){
            $v = $this->getValue($answer->getValueDecimal());
        }

        if($v === null){
            $v = $this->getValue($answer->getValueBoolean());
        }

        if($v === null && $answer->getValueCoding()){
            $v = $answer->getValueCoding()->getCode();
        }

        return $v;
    }

    function walkQuestionnaire($parents, $fieldAction){
        if(!is_array($parents)){
            if($parents->_getFHIRTypeName() === 'Questionnaire'){
                // This is expected on the initial call.
                $parents = [$parents];
            }
            else{
                throw new Exception("An array of parent resources was expected.");
            }
        }

        $group = end($parents);
        $groupId = $this->getLinkId($group);

        foreach($group->getItem() as $item){
            $id = $item->getLinkId()->getValue()->getValue();
            if(in_array($item->getType()->getValue()->getValue()->getValue(), ['group', 'display'])){
                $newParents = $parents;
                $newParents[] = $item;
                self::walkQuestionnaire($newParents, $fieldAction);
            }
            else{
                $fieldAction($parents, $item);
            } 
        }
    }

    function isRepeating($item){
        if($item->_getFHIRTypeName() !== 'Questionnaire.Item'){
            return false;
        }

        $repeats = $item->getRepeats();
        return $repeats && $repeats->getValue()->getValue();
    }

    function getLinkId($item){
        if(!in_array($item->_getFHIRTypeName(), ['Questionnaire.Item', 'QuestionnaireResponse.Item'])){
            return null;
        }

        return $item->getLinkId()->getValue()->getValue();
    }

    function getType($item){
        if($item->_getFHIRTypeName() === 'Questionnaire.Item'){
            $type = $item->getType();
            if($type){
                $type = $type->getValue()->getValue()->getValue();
                if(in_array($type, ['string', 'integer', 'decimal', 'dateTime'])){
                    $type = 'text';
                }
                else if($type === 'text'){
                    $type = 'notes';
                }
                else if(in_array($type, ['choice', 'open-choice'])){
                    return 'dropdown';
                }
                else if($type === 'boolean'){
                    return 'yesno';
                }
                else if($type === 'attachment'){
                    return 'file';
                }
                else{
                    throw new Exception("Type not supported: $type");
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

    function getInstrumentName($group, $item = null){
        if($item && $this->isRepeating($item)){
            $group = $item;
        }
        
        $text = strtolower($this->getText($group));
        if(empty($text)){
            $text = "top_level_questions";
        }

        $name = 'q' . $this->getLinkId($group) . '_' . $text;
        $name = str_replace(' ', '_', $name);
        $name = str_replace('.', '_', $name);
        $name = str_replace(',', '_', $name);
        $name = str_replace('/', '_', $name);
        $name = str_replace('-', '_', $name);
        $name = str_replace('\'', '_', $name);
        $name = str_replace('(', '', $name);
        $name = str_replace(')', '', $name);
        $name = str_replace(':', '', $name);
        $name = str_replace('?', '', $name);
        $name = substr($name, 0, 64);

        return $name;
    }
    
    function getQuestionnaireUrl($section){
        return $this->getUrl("questionnaire-options.php?section=$section");
    }

    function getExtensionForMIMEType($contentType){
        if($contentType === 'application/pdf'){
            $extension = 'pdf';
        }
        else{
            $extension = null;
        }

        return $extension;
    }

    function getReceivedResources($whereClause = ''){
        return $this->queryLogs("
            select log_id, timestamp, type, content
            where project_id is null
            and message = '" . RESOURCE_RECEIVED . "'
            $whereClause
            order by log_id desc
        ");
    }

    function getReceivedResource($logId){
        $logId = db_escape($logId);
        $result = $this->getReceivedResources("and log_id = $logId");
        return $result->fetch_assoc();
    }

    function getFHIRResourceForRecord($projectId, $recordId){
        $type = $this->getProjectType($projectId);
        if($type === 'questionnaire'){
            return $this->buildQuestionnaireResponse($projectId, $recordId);
        }
    }

    function buildQuestionnaireResponse($projectId, $recordId){
        $instances = $this->getData($projectId, $recordId);
        if(empty($instances)){
            throw new Exception("A resource with the specified ID does not exist.");
        }

        $data = [];
        foreach($instances as $instanceData){
            $currentRecordId = current($instanceData);
            if($currentRecordId !== $recordId){
                throw new Exception("Expected record ID $recordId, but found $currentRecordId!");
            }

            $instance = $instanceData['redcap_repeat_instance'];
            if(!$instance){
                $instance = 1;
            }
            
            unset($instanceData[key($instanceData)]);
            unset($instanceData['redcap_repeat_instrument']);
            unset($instanceData['redcap_repeat_instance']);

            foreach($instanceData as $fieldName=>$value){
                if($value !== ''){
                    if(isset($data[$fieldName][$instance])){
                        throw new Exception("A value is already set for instance $instance of the $fieldName field!");
                    }

                    $data[$fieldName][$instance] = $value;
                }
            }
        }

        $edoc = $this->getQuestionnaireEDoc($projectId);

        $questionnaire = $this->parse(file_get_contents(EDOC_PATH . $edoc['stored_name']));

        $questionnaireResponse = new FHIRQuestionnaireResponse([
            'status' => 'completed'
        ]);

        $questionnaireResponse = $this->addIdentifier($questionnaireResponse, $projectId, $recordId);

        $responseObjects = [];
        $getResponseObject = function($parentResponseItem, $item, $instanceIndex) use (&$responseObjects, $questionnaireResponse){
            if(!$parentResponseItem){
                // This is the Questionnaire root
                return $questionnaireResponse;
            }

            $itemId = $this->getLinkId($item);
            $responseItem = $responseObjects[$itemId][$instanceIndex];
            if(!$responseItem){      
                $responseItem = new FHIRQuestionnaireResponseItem;
                $responseItem->setLinkId($itemId);
                $responseItem->setText($item->getText());

                $responseObjects[$itemId][$instanceIndex] = $responseItem;
                $parentResponseItem->addItem($responseItem);
            }

            return $responseItem;
        };

        $this->walkQuestionnaire($questionnaire, function($parents, $item) use ($data, &$getResponseObject){
            $fieldName = $this->getFieldName($item);
            $instances = $data[$fieldName];
            foreach($instances as $instance=>$value){
                $items = array_merge($parents, [$item]);
                $lastResponseItem = null; 
                $parentInstance = 1;
                foreach($items as $item){
                    if(self::isRepeating($item)){
                        $parentInstance = $instance;
                    }

                    $responseItem = $getResponseObject($lastResponseItem, $item, $parentInstance-1);
                    $lastResponseItem = $responseItem;
                }

                $type = $this->getValue($item->getType());
                if(in_array($type, ['string', 'text'])){
                    $answerData = ['valueString' => $value];
                }
                else if($type === 'integer'){
                    $answerData = ['valueInteger' => $value];
                }
                else if($type === 'decimal'){
                    $answerData = ['valueDecimal' => $value];
                }
                else if($type === 'boolean'){
                    $answerData = ['valueBoolean' => $value === '1'];
                }
                else if($type === 'dateTime'){
                    $answerData = ['valueDateTime' => $this->formatTimestamp(strtotime("$value UTC"))];
                }
                else if(in_array($type, ['choice', 'open-choice'])){
                    $answerData = [
                        'valueCoding' => new FHIRCoding([
                            'code' => $value,
                            'display' => $this->getAnswers($item)[$value]
                        ])
                    ];
                }
                else{
                    throw new Exception("Type not supported: $type");
                }

                $responseItem->addAnswer(new FHIRQuestionnaireResponseAnswer($answerData));
            }
        });

        return $questionnaireResponse;
    }

    function getRemoteFHIRServerUrl(){
        $url = $this->getProjectSetting('remote-fhir-server-url');
        if(empty($url)){
            throw new Exception('A remote FHIR server url must be configured.');
        }

        return $url;
    }
}