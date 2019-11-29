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
                            if(response === 'success'){
                                alert('The data was successfully sent to the remote FHIR server.')
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
            $csv = $this->questionnaireToDataDictionary($file['tmp_name']);
            file_put_contents($dictionaryPath, $csv);

            require_once APP_PATH_DOCROOT . '/Design/functions.php';
            $dictionary_array = excel_to_array($dictionaryPath);
        }
        finally{
            unlink($dictionaryPath);
        }

        list ($errors_array, $warnings_array, $dictionary_array) = MetaData::error_checking($dictionary_array);

        $checkForErrors = function($errors, $type, $runOnError = null){
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

        $edocId = \Files::uploadFile($file, $this->getProjectId());
        $this->setProjectSetting('questionnaire', $edocId);
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

    function getInstant(){
        return date('Y-m-d\TH:i:sP');
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
            'timestamp' => $this->getInstant(),
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
            'date' => $this->getInstant(), // TODO - This should pull the last edit time from the log instead.
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
        $this->walkQuestionnaire($q, function($parents, $item) use (&$forms){
            $fieldName = $this->getFieldName($item);
            $instrumentName = $this->getInstrumentName(end($parents));
            if(empty($instrumentName)){
                $instrumentName = "top_level_questions";
            }

            $forms[$instrumentName][$fieldName] = [
                'type' => $this->getType($item),
                'label' => $this->getText($item),
                'choices' => $this->getREDCapChoices($item)
            ];
        });
        
        $out = fopen('php://memory', 'r+');
        fputcsv($out, ["Variable / Field Name","Form Name","Section Header","Field Type","Field Label","Choices, Calculations, OR Slider Labels","Field Note","Text Validation Type OR Show Slider Number","Text Validation Min","Text Validation Max","Identifier?","Branching Logic (Show field only if...)","Required Field?","Custom Alignment","Question Number (surveys only)","Matrix Group Name","Matrix Ranking?","Field Annotation"]);
        fputcsv($out, ['response_id', current(array_keys($forms)), '', 'text', 'Response ID']);

        foreach($forms as $formName=>$fields){
            foreach($fields as $name=>$field){
                fputcsv($out, [$name, $formName, '', $field['type'], $field['label'], $field['choices']]);
            }
        }

        rewind($out);

        return stream_get_contents($out);
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

        $data = ['response_id' => 'TBD'];

        $handleObject = function($parent) use (&$handleObject, &$data){
            foreach($parent->getItem() as $item){
                $answers = $item->getAnswer();
                if(empty($answers)){
                    $handleObject($item);
                }
                else{
                    foreach($answers as $answer){
                        $data[$this->getFieldName($item)] = $this->getAnswerValue($item, $answer);
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
        $v = $this->getValue($answer->getValueString());

        if($this->getText($item) === 'Last Updated at:'){
            $v = DateTime::createFromFormat('F j, Y \a\t g:i A e', $v)->format('Y-m-d H:i');
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
                if($this->isRepeating($item)){
                    throw new Exception("The following field repeats, which is only supported for groups currently: $id");
                }

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

    function getInstrumentName($group){
        $name = strtolower($this->getText($group));
        $name = str_replace(' ', '_', $name);
        $name = str_replace('(', '', $name);
        $name = str_replace(')', '', $name);
        $name = str_replace(':', '', $name);
        $name = str_replace('?', '', $name);

        return $name;
    }

    function getSafePath($path, $root=null){
		$moduleDirectory = $this->getModulePath();
		if(!$root){
			$root = $moduleDirectory;
		}
		else if(!file_exists($root)){
			$root = "$moduleDirectory/$root";
		}

		if(!file_exists($root)){
			throw new Exception("The specified root ($root) does not exist as either an absolute path or a relative path to the module directory.");
		}

		$root = realpath($root);

		$fullPath = "$root/$path";

		if(file_exists($fullPath)){
			$fullPath = realpath($fullPath);
		}
		else{
			// Also support the case where this is a path to a new file that doesn't exist yet and check it's parents.
			$dirname = dirname($fullPath);
				
			if(!file_exists($dirname)){
				throw new Exception("The parent directory ($dirname) does not exist.  Please create it before calling getSafePath() since the realpath() function only works on directories that exist.");
			}

			$fullPath = realpath($dirname) . DIRECTORY_SEPARATOR . basename($fullPath);
		}

		if(strpos($fullPath, $root) !== 0){
			throw new Exception("You referenced a path ($fullPath) that is outside of your allowed parent directory ($root).");
		}

		return $fullPath;
    }
    
    function getQuestionnaireUrl($section){
        return $this->getUrl("questionnaire-options.php?section=$section");
    }

    function getReceivedQuestionnaires($whereClause = ''){
        return $this->queryLogs("
            select log_id, timestamp, type, content
            where project_id is null
            and message = '" . RESOURCE_RECEIVED . "'
            and type in('Questionnaire', 'QuestionnaireResponse');
            $whereClause
            order by log_id desc
        ");
    }

    function getReceivedQuestionnaire($logId){
        $logId = db_escape($logId);
        $result = $this->getReceivedQuestionnaires("and log_id = $logId");
        return $result->fetch_assoc();
    }

    function getFHIRResourceForRecord($projectId, $recordId){
        $type = $this->getProjectType($projectId);
        if($type === 'questionnaire'){
            return $this->buildQuestionnaireResponse($projectId, $recordId);
        }
    }

    function buildQuestionnaireResponse($projectId, $recordId){
        $data = $this->getData($projectId, $recordId)[0];
        if(empty($data)){
            throw new Exception("A resource with the specified ID does not exist.");
        }

        $edoc = $this->getQuestionnaireEDoc($projectId);

        $questionnaire = $this->parse(file_get_contents(EDOC_PATH . $edoc['stored_name']));

        $questionnaireResponse = new FHIRQuestionnaireResponse([
            'status' => 'completed'
        ]);

        $questionnaireResponse = $this->addIdentifier($questionnaireResponse, $projectId, $recordId);

        $responseObjects = [];
        $getResponseObject = function($parentResponseItem, $item) use (&$responseObjects, $questionnaireResponse){
            if(!$parentResponseItem){
                // This is the Questionnaire root
                return $questionnaireResponse;
            }

            $itemId = $this->getLinkId($item);
            $responseItem = $responseObjects[$itemId];
            if(!$responseItem){      
                $responseItem = new FHIRQuestionnaireResponseItem;
                $responseItem->setLinkId($itemId);
                $responseItem->setText($item->getText());

                $responseObjects[$itemId] = $responseItem;
                $parentResponseItem->addItem($responseItem);
            }

            return $responseItem;
        };

        $this->walkQuestionnaire($questionnaire, function($parents, $item) use ($data, &$getResponseObject){
            $fieldName = $this->getFieldName($item);
            $value = $data[$fieldName];
            if(!$value){
                return;
            }

            $items = array_merge($parents, [$item]);
            $lastResponseItem = null; 
            foreach($items as $item){
                $responseItem = $getResponseObject($lastResponseItem, $item);
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
            else if(in_array($type, ['choice', 'open-choice'])){
                $answerData = [
                    'valueCoding' => new FHIRCoding([
                        'code' => $value,
                        'display' => $this->getAnswers($item)[$value],
                        'system' => 'Custom'
                    ])
                ];
            }
            else{
                throw new Exception("Type not supported: $type");
            }

            $responseItem->addAnswer(new FHIRQuestionnaireResponseAnswer($answerData));
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