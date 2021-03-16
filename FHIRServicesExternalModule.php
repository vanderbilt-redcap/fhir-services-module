<?php namespace Vanderbilt\FHIRServicesExternalModule;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/SchemaParser.php';
require_once __DIR__ . '/StackFreeException.php';
require_once __DIR__ . '/classes/FieldMapper.php';

use REDCap;
use DateTime;
use MetaData;
use Exception;
use Form;

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
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRQuestionnaire;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRResearchStudy;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRPractitionerRole;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRQuestionnaireResponse;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRBundle\FHIRBundleEntry;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRComposition\FHIRCompositionSection;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIROrganization\FHIROrganizationContact;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRQuestionnaire\FHIRQuestionnaireItem;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRQuestionnaireResponse\FHIRQuestionnaireResponseItem;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRQuestionnaireResponse\FHIRQuestionnaireResponseAnswer;

const RESOURCE_RECEIVED = 'Resource Received';
const FHIR_GROUP = 'fhir-group';
const SUPPORTED_ACTION_TAGS = [
    '@DEFAULT',
    '@CHARLIMIT',
    '@HIDECHOICE',
    '@READONLY',
    '@HIDDEN'
];

const ACTION_TAG_PREFIX = "@FHIR-MAPPING='";
const ACTION_TAG_SUFFIX = "'";
const SINGLE_QUOTE_PLACEHOLDER = '<single-quote-placeholder>';

const INTEGER_PATTERN = "^-?([0]|([1-9][0-9]*))$";
const DATE_TIME_PATTERN = "^([0-9]([0-9]([0-9][1-9]|[1-9]0)|[1-9]00)|[1-9]000)(-(0[1-9]|1[0-2])(-(0[1-9]|[1-2][0-9]|3[0-1])(T([01][0-9]|2[0-3]):[0-5][0-9]:([0-5][0-9]|60)(\\.[0-9]+)?(Z|(\\+|-)((0[0-9]|1[0-3]):[0-5][0-9]|14:00)))?)?)?$";

class FHIRServicesExternalModule extends \ExternalModules\AbstractExternalModule{
    function redcap_every_page_top(){
        if($this->isPage('DataEntry/record_home.php')){
            $this->hookRecordHome();
        }
        else if($this->isPage('Design/online_designer.php')){
            $this->hookOnlineDesigner();
        }
    }

    private function isPage($path){
        $path = APP_PATH_WEBROOT . $path;
        return strpos($_SERVER['REQUEST_URI'], $path) === 0;
    }

    private function hookOnlineDesigner(){
        $this->getEditFieldModifications();
        ?>
        <script>
            $(function(){
                var menu = $('#formActionDropdown')
                if(menu.length === 0){
                    // We're not on the main online designer page.
                    return
                }

                var zipDownloadItem = menu.find('li:last-child')[0]
                
                var newItem = $(zipDownloadItem).clone()
                newItem.removeAttr('id')
                newItem.find('span').html('Download instrument as FHIR Questionnaire')                
                newItem.find('a')[0].onclick = function(){
                    var url = <?=json_encode($this->getUrl('questionnaire/instrument-to-questionnaire.php'))?>;
                    url += '&form=' + $('#ActionCurrentForm').val()

                    // Download the file before displaying the alert.
                    // The browser may consider the download a popup and block it if the user takes too long to dismiss the alert.
                    window.open(url);

                    $.get(url + '&return-warnings', function(warnings){
                        var message = ''

                        var handleWarnings = function(items, itemMessage){
                            var separator = '\n        '
                            if(items.length === 0){
                                return
                            }

                            message += itemMessage + ':\n'
                            message += separator + items.join(separator)
                            message += '\n\n'
                        }

                        handleWarnings(warnings.skippedFields, 'The following fields were excluded from the downloaded Questionnaire because their type and/or validation settings are not yet supported')
                        handleWarnings(warnings.unsupportedActionTags, 'The following action tags were ignored because they are not currently supported')

                        if(message.length > 0){
                            alert(message)
                        }
                    })
                }
                
                newItem.insertAfter(zipDownloadItem)
            })
        </script>
        <?php
    }

    private function getEditFieldModifications(){
        ?>
        <style>
            .ui-autocomplete.fhir-services-module{
                max-width: 298px;
                max-height: 208px;  
            }
            .ui-autocomplete.fhir-services-module li{
                white-space: normal !important;
            }
            #fhir-services-mapping-field-settings > b{
                margin-bottom: 5px;
                display: block;
            }
            #fhir-services-mapping-field-settings table,
            #fhir-services-mapping-field-settings td:last-child,
            #fhir-services-mapping-field-settings td input{
                width: 100%;
            }
            #fhir-services-mapping-field-settings td:first-child{
                text-align: right;
            }
            #fhir-services-mapping-field-settings td label{
                margin-right: 4px;
                margin-bottom: 0px;
            }
            .fhir-services-additional-element-header{
                display: block;
                margin-top: 12px;
                margin-bottom: 5px;
            }
            .fhir-services-additional-element-wrapper{
                margin-left: 3px;
            }
            #fhir-services-additional-elements .fhir-services-additional-element-wrapper{
                border: 1px solid rgb(211, 211, 211);
                background: #ececec;
                margin-left: 5px;
                margin-right: 16px;
                margin-bottom: -1px;
                padding: 4px;
                padding-left: 4px;
            }
            .fhir-services-remove-additional-element{
                display: block;
                height: 0px;
                position: relative;
                left: 25px;
                top: 16px;
                text-align: right !important;
            }
            #fhir-services-additional-element-buttons{
                margin: 6px;
                text-align: center;
            }
            #fhir-services-additional-element-buttons button{
                margin: 0px 2px;
            }
            #fhir-services-mapping-field-settings a,
            #fhir-services-invalid-choices-dialog a{
                text-decoration: underline;
                outline : none;
            }
            #fhir-services-invalid-choices-dialog ul{
                margin-top: 10px;
            }
            #fhir-services-recommended-choices-dialog .textarea-wrapper{
                margin-top: 10px;
                text-align: center;
            }
            #fhir-services-recommended-choices-dialog textarea{
                height: 150px;
                width: 85%;
            }
            #fhir-services-mapping-field-settings .fhir-services-recommended-choices-link{
                margin-left: 21%;
                margin-bottom: 3px;
                display: inline-block;
            }
        </style>
        <script>
            var FHIRServicesExternalModule = <?=json_encode([
                'schema' => SchemaParser::getModifiedSchema(),
                'ACTION_TAG_PREFIX' => ACTION_TAG_PREFIX,
                'ACTION_TAG_SUFFIX' => ACTION_TAG_SUFFIX,
                'APP_PATH_IMAGES' => APP_PATH_IMAGES
            ])?>
        </script>
        <script src="<?=$this->getUrl('module.js')?>" />
        <?php
    }

    private function hookRecordHome(){
        $projectId = $this->getProjectId();
        $recordId = $_GET['id'];

        $projectType = $this->getProjectType();
        $resourceName = null;
        if($projectType === 'questionnaire'){
            $resourceName = 'QuestionnaireResponse';
        }
        else{
            $resourceName ='Bundle';
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
                            alert('The remote FHIR server has confirmed that the data has been successfully received.')
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
                    var lastPdfOption = $(dropdown.find('a:contains("Download PDF")').toArray().reverse()[0]).parent()
                    
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
                            window.open(<?=json_encode($this->getResourceUrl([
                                'resourceType' => $resourceName,
                                'id' => $this->getRecordFHIRId($projectId, $recordId)
                            ]))?>)
                        }
                    }

                    addOption('Open FHIR ' + resourceName, 'file', openAction)
                
                    var projectType = <?=json_encode($projectType)?>;
                    if(projectType){
                        // This is one of the projects used by the SIRB features/demo.
                        addOption('Send FHIR ' + resourceName + ' to remote FHIR server', 'file-export', function(){
                            sendRecord(false)
                        })
                    }
                })
            })()
        </script>
        <?php
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

    function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance){
        $Proj = new \Project($project_id);
		$surveySettings = $Proj->surveys[$Proj->forms[$instrument]['survey_id']];
        $isEConsentEnabled = $surveySettings['pdf_auto_archive'] === '2';
        ?>
        <script>
            $(function(){
                if(<?=json_encode($isEConsentEnabled)?>){
                    var newButton = $('<button style="margin-left: 15px" class="jqbuttonmed ui-button ui-corner-all ui-widget">View eConsent FHIR Bundle</button>')
                    newButton.click(function(){
                        window.open(<?=
                            json_encode(
                                $this->getUrl('view-econsent-fhir-bundle.php') .
                                "&record=$record" .
                                "&form=$instrument" .
                                "&event=$event_id" .
                                "&instance=$repeat_instance"
                            )
                        ?>)

                        return false
                    })

                    $('#form_response_header div:last-child').append(newButton)
                }
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
            list($csv, $formDisplayNames, $repeatingFormNames) = $this->questionnaireToDataDictionary($file['tmp_name']);
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

        foreach($formDisplayNames as $formName=>$formDisplayName){
            $this->setFormName($this->getProjectId(), $formName, $formDisplayName);
        }

        $eventId = $this->getEventId();
        
        $this->query('delete from redcap_events_repeat where event_id = ?', $eventId);
        
        foreach($repeatingFormNames as $formName){
            $this->query('insert into redcap_events_repeat (event_id, form_name) values (?, ?)', [$eventId, $formName]);
        }

        $edocId = \Files::uploadFile($file, $this->getProjectId());
        $this->setProjectSetting('questionnaire', $edocId);
    }

    private function getProjectStatus($project_id){
		$result = db_query("select status from redcap_projects where project_id = '" . db_escape($project_id) . "'");
		
		$row = $result->fetch_assoc();
		if(!$row){
			throw new Exception("Could not find status for project: $project_id");
		}

		return (int) $row['status'];
	}

    private function setFormName($project_id, $form_name, $new_form_name){
		$status = self::getProjectStatus($project_id);
		
		//If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
		$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";

		## Set the new form menu name
		$menu_description = strip_tags(label_decode($new_form_name));
		// First set all form_menu_description as null
		$sql = "update $metadata_table set form_menu_description = null where form_name = '".db_escape($form_name)."' and project_id = $project_id";
		$q1 = db_query($sql);
		// Get lowest field_order in form
		$sql = "select field_name from $metadata_table where form_name = '".db_escape($form_name)."' and project_id = $project_id order by field_order limit 1";
		$q1 = db_query($sql);
		$min_field_order_var = db_result($q1, 0);
		// Now add the new form menu label
		$sql = "update $metadata_table set form_menu_description = '".db_escape($menu_description)."'
				where field_name = '$min_field_order_var' and project_id = $project_id";
		$q1 = db_query($sql);

		// As a default, the form_name stays the same value
		$new_form_name = $form_name;

		## If in DEVELOPMENT ONLY, change the back-end form name value based upon the form menu name and ensure uniqueness
		// Cannot do this in production because of issues with form name being tied to Form Status field)
		if ($status < 1)
		{
			$new_form_name = preg_replace("/[^a-z0-9_]/", "", str_replace(" ", "_", strtolower(html_entity_decode($menu_description, ENT_QUOTES))));
			// Remove any double underscores, beginning numerals, and beginning/ending underscores
			while (strpos($new_form_name, "__") !== false) 		$new_form_name = str_replace("__", "_", $new_form_name);
			while (substr($new_form_name, 0, 1) == "_") 		$new_form_name = substr($new_form_name, 1);
			while (substr($new_form_name, -1) == "_") 			$new_form_name = substr($new_form_name, 0, -1);
			while (is_numeric(substr($new_form_name, 0, 1))) 	$new_form_name = substr($new_form_name, 1);
			while (substr($new_form_name, 0, 1) == "_") 		$new_form_name = substr($new_form_name, 1);
			// Cannot begin with numeral and cannot be blank
			if (is_numeric(substr($new_form_name, 0, 1)) || $new_form_name == "") {
				$new_form_name = substr(preg_replace("/[0-9]/", "", md5($new_form_name)), 0, 4) . $new_form_name;
			}
			// Make sure it's less than 50 characters long
			$new_form_name = substr($new_form_name, 0, 50);
			while (substr($new_form_name, -1) == "_") $new_form_name = substr($new_form_name, 0, -1);
			// Make sure this form value doesn't already exist
			if ($new_form_name != $form_name) {
				$formExists = ($status > 0) ? isset($Proj->forms_temp[$new_form_name]) : isset($Proj->forms[$new_form_name]);
				while ($formExists) {
					// Make sure it's less than 64 characters long
					$new_form_name = substr($new_form_name, 0, 45);
					// Append random value to form_name to prevent duplication
					$new_form_name .= "_" . substr(sha1(rand()), 0, 4);
					// Try again
					$formExists = ($status > 0) ? isset($Proj->forms_temp[$new_form_name]) : isset($Proj->forms[$new_form_name]);
				}
			}
			// Change back-end form name in metadata table
			$sql = "update $metadata_table set form_name = '".db_escape($new_form_name)."' where form_name = '".db_escape($form_name)."' and project_id = $project_id";
			db_query($sql);
			// Get event_ids
			$eventIds = pre_query("select m.event_id from redcap_events_arms a, redcap_events_metadata m where a.arm_id = m.arm_id and a.project_id = $project_id");
			// Change back-end form name in event_forms table
			$sql = "update redcap_events_forms set form_name = '".db_escape($new_form_name)."' where form_name = '".db_escape($form_name)."'
					and event_id in ($eventIds)";
			db_query($sql);
			// Change back-end form name in redcap_events_repeat table
			$sql = "update redcap_events_repeat set form_name = '".db_escape($new_form_name)."' where form_name = '".db_escape($form_name)."'
					and event_id in ($eventIds)";
			db_query($sql);
			// Change back-end form name in user_rights table
			$sql = "update redcap_user_rights set data_entry = replace(data_entry, '[{$form_name},', '[$new_form_name,')
					where project_id = $project_id";
			db_query($sql);
			// Change back-end form name in library_map table
			$sql = "update redcap_library_map set form_name = '".db_escape($new_form_name)."' where project_id = $project_id and form_name = '".db_escape($form_name)."'";
			db_query($sql);
			// Change back-end form name in locking tables
			$sql = "update redcap_locking_labels set form_name = '".db_escape($new_form_name)."' where project_id = $project_id and form_name = '".db_escape($form_name)."'";
			db_query($sql);
			$sql = "update redcap_locking_data set form_name = '".db_escape($new_form_name)."' where project_id = $project_id and form_name = '".db_escape($form_name)."'";
			db_query($sql);
			$sql = "update redcap_esignatures set form_name = '".db_escape($new_form_name)."' where project_id = $project_id and form_name = '".db_escape($form_name)."'";
			db_query($sql);
			// Change back-end form name in survey table
			$sql = "update redcap_surveys set form_name = '".db_escape($new_form_name)."' where project_id = $project_id and form_name = '".db_escape($form_name)."'";
			db_query($sql);
			// Change variable name of the form's Form Status field
			$sql = "update $metadata_table set field_name = '{$new_form_name}_complete' where field_name = '{$form_name}_complete' and project_id = $project_id";
			db_query($sql);
			// Change actual data table field_names to reflect the changed Form Status field
			$sql = "update redcap_data set field_name = '{$new_form_name}_complete' where field_name = '{$form_name}_complete' and project_id = $project_id";
			db_query($sql);
			// Change alerts tables
			$alertIds = pre_query("select alert_id from redcap_alerts where project_id = $project_id and form_name = '".db_escape($form_name)."'");
			$sql = "update redcap_alerts set form_name = '".db_escape($new_form_name)."' where project_id = $project_id and form_name = '".db_escape($form_name)."'";
			db_query($sql);
			$sql = "update redcap_alerts_recurrence set instrument = '".db_escape($new_form_name)."' where alert_id in ($alertIds) and instrument = '".db_escape($form_name)."'";
			db_query($sql);
			$sql = "update redcap_alerts_sent set instrument = '".db_escape($new_form_name)."' where alert_id in ($alertIds) and instrument = '".db_escape($form_name)."'";
			db_query($sql);
		}

		// Get survey title, if enabled as a survey
		$surveyTitle = "";
		if ($surveys_enabled) {
			$sql = "select title from redcap_surveys where project_id = $project_id and form_name = '".db_escape($new_form_name)."' limit 1";
			$q = db_query($sql);
			if (db_num_rows($q)) {
				$surveyTitle = strip_tags(label_decode(db_result($q, 0)));
			}
		}

		// Logging
		if ($q1) \Logging::logEvent("",$metadata_table,"MANAGE",$form_name,"form_name = '".db_escape($form_name)."'","Rename data collection instrument");

		return [$new_form_name, $menu_description, $surveyTitle];
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
        $result = $this->query("select * from redcap_edocs_metadata where doc_id = ?", $edocId);
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
            where project_id = ?
            and field_name = ?
        ";

        $result = $this->query($sql, [$pid, $fieldName]);

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
            throw new Exception("The resource ID specified is not valid: $resourceId");
        }

        return [$projectId, $recordId]; 
    }

    function getResourceUrl($resource){
        $originalPid = $_GET['pid'];
        unset($_GET['pid']);

        // Get the URL without the pid.
        $urlPrefix = $this->getUrl('service.php', true, true);

        $_GET['pid'] = $originalPid;

        return "$urlPrefix&fhir-url=/" . $this->getRelativeResourceUrl($resource);
    }

    function getRelativeResourceUrl($resource){
        $type = $resource['resourceType'];
        if(empty($type)){
            throw new Exception("A 'resourceType' is required to build URLs.");
        }

        $id = $resource['id'];
        if(empty($id)){
            throw new Exception("An 'id' is required to build URLs.");
        }

        return "$type/$id";
    }

    function getRecordFHIRId($projectId, $recordId){
        return "$projectId-$recordId";
    }

    function getInstanceFHIRId($pid, $record, $event, $form, $instance){
        $form = str_replace('_', '-', $form);
        return $this->getRecordFHIRId($pid, $record) . "-$event-$form-$instance";
    }

    function addIdentifier($resource, $projectId, $recordId){
        if($resource->getId()){
            throw new Exception('The ID is already set!');
        }

        $resourceId = $this->getRecordFHIRId($projectId, $recordId);

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

    function formatFHIRDateTime($timestamp){
        return $this->getDateTime($timestamp)->format('Y-m-d\TH:i:sP');
    }

    function formatFHIRTime($timestamp){
        return $this->getDateTime($timestamp)->format('H:i:s');
    }

    function formatREDCapDateTime($mixed){
        return $this->getDateTime($mixed)->format('Y-m-d H:i');
    }

    function formatREDCapDateTimeWithSeconds($mixed){
        return $this->getDateTime($mixed)->format('Y-m-d H:i:s');
    }

    function formatREDCapTime($mixed){
        return $this->getDateTime($mixed)->format('H:i');
    }

    private function getDateTime($mixed){
        $type = gettype($mixed);

        if($type === 'string'){
            return new DateTime($mixed);
        }
        else if($type === 'integer'){
            $d = new DateTime();
            $d->setTimestamp($mixed);
            return $d;
        }
        else{
            // Assume this is already a DateTime object.
            return $mixed;
        }
    }

    function isFHIRResource($o){
        return $o instanceof FHIRResource;
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
            'timestamp' => $this->formatFHIRDateTime(time()),
            'type' => [
                'value' => 'document'
            ],
            'meta' => self::createMeta('Determination')
        ]);

        $this->addIdentifier($bundle, $compositionsPid, $compositionId);

        $getReferenceString = function($resource){
            if(!$this->isFHIRResource($resource)){
                throw new Exception('The object specified was not a FHIR resource: ' . json_encode($resource));
            }

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
                if($this->isFHIRResource($param)){
                    $section->addEntry($getReference($param));
                }
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
            'date' => $this->formatFHIRDateTime(time()), // TODO - This should pull the last edit time from the log instead.
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
        $composition->addSection($getCompositionSection('title', [
            'irexLogoUrl' => $this->getUrl('irex_logo.svg')
        ]));
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
        $formDisplayNames = [];
        $repeatingFormNames = [];
        $this->walkQuestionnaire($q, function($parents, $item) use (&$forms, &$formDisplayNames, &$repeatingFormNames){
            $fieldName = $this->getFieldName($item);
            $parent = end($parents);

            $instrumentName = $this->getInstrumentName($parent, $item);
            $path = self::getQuestionnairePath($parents, $instrumentName);
            $form = &$forms[$path];
            if(!$form){
                if($formDisplayNames[$instrumentName]){
                   throw new Exception("Two forms exist with the following name: $instrumentName");
                }
                
                $formDisplayNames[$instrumentName] = $this->getInstrumentName($parent, $item, true);

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
                'label' => $this->getText($item),
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
            $formDisplayNames,
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

    function parseREDCapChoices($redcapField){
        $choices = explode("\\n", $redcapField['element_enum']);
        $valueMap = [];
        foreach($choices as $choice){
            $separator = ',';
            $separatorIndex = strpos($choice, $separator);

            $code = trim(substr($choice, 0, $separatorIndex));
            $display = trim(substr($choice, $separatorIndex+strlen($separator)));

            $valueMap[$code] = $display;
        }

        return $valueMap;
    }

    function getFHIRAnswerOptions($redcapField){
        $type = $redcapField['element_type'];
        $valueMap = [];
        if($type === 'yesno'){
            $valueMap['1'] = 'Yes';
            $valueMap['0'] = 'No';
        }
        else if($type === 'truefalse'){
            $valueMap['1'] = 'True';
            $valueMap['0'] = 'False';
        }
        else{
            $valueMap = $this->parseREDCapChoices($redcapField);
        }

        $answerOptions = [];
        foreach($valueMap as $code=>$display){
            $answerOptions[] = [
                'valueCoding' => [
                    'code' => strval($code),
                    'display' => $display,
                ]
            ];
        }

        return $answerOptions;
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

    function questionnaireResponseToREDCapExport($jsonOrXml){
        $o = $this->parse($jsonOrXml);
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
                        $value = $this->getTypedValue($answer);
                        
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

    function getRepeatingForms(){
        $result = $this->query('select * from redcap_events_repeat where event_id = ?', $this->getEventId());

        $forms = [];
        while($row = $result->fetch_assoc()){
            $forms[] = $row['form_name'];
        }

        return $forms;
    }

    function getTypedValue($mixed){
        $v = $this->getValue($mixed->getValueString());
        
        if($v === null){
            $v = $this->getValue($mixed->getValueDateTime());

            if(!empty($v)){
                $v = $this->formatREDCapDateTime($v);
            }
        }

        if($v === null){
            $v = $this->getValue($mixed->getValueTime());

            if(!empty($v)){
                $v = $this->formatREDCapTime($v);
            }
        }

        if($v === null){
            $v = $this->getValue($mixed->getValueDate());
        }

        if($v === null){
            $v = $this->getValue($mixed->getValueInteger());
        }

        if($v === null){
            $v = $this->getValue($mixed->getValueDecimal());
        }

        if($v === null){
            $v = $this->getValue($mixed->getValueBoolean());
        }

        if($v === null && $mixed->getValueCoding()){
            $v = $mixed->getValueCoding()->getCode();
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

            $type = $this->getValue($item->getType());
            if(in_array($type, ['group', 'display'])){
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
            $type = $this->getValue($item->getType());
            if($type){
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
                    throw new Exception("FHIR Questionnaire Item type not supported: $type");
                }

                return $type;
            }
        }
    }

    function getFHIRType($redcapField){
        $type = $redcapField['element_type'];
        $validation = $redcapField['element_validation_type'];

        if($type === 'text'){
            if(empty($validation)){
                return 'string';
            }
            else if(strpos($validation, 'date_') === 0){
                return 'date';
            }
            else if(strpos($validation, 'datetime_') === 0){
                return 'dateTime';
            }
            else if($validation === 'int'){
                return 'integer';
            }
            else if($validation === 'float'){
                return 'decimal';
            }
            else if($validation === 'time'){
                return 'time';
            }
        }
        else if($type === 'textarea'){
            return 'text';
        }
        else if($type === 'calc'){
            // not currently supported
        }
        else if(in_array($type, ['select', 'radio', 'yesno', 'truefalse'])){
            return 'choice';
        }
        else if($type === 'checkbox'){
            return 'boolean';
        }
        else if($type === 'file' && empty($validation)){
            return 'attachment';
        }
        else if($type === 'slider'){
            // not currently supported
        }
        else if($type === 'descriptive'){
            return 'display';
        }
        else if($type === 'sql'){
            // not currently supported
        }
        else if($type === FHIR_GROUP){
            // This is not a real REDCap field type, but we use it for convenience within this module.
            return FHIR_GROUP;
        }

        return null;
    }

    function getText($item){
        $text = $item->getText();
        if($text){
            return $text->getValue()->getValue();
        }
    }

    function getInstrumentName($group, $item = null, $displayName = false){
        if($item && $this->isRepeating($item)){
            $group = $item;
        }

        $name = $this->getText($group);
        $linkId = null;
        if(empty($name)){
            $name = "Top Level Questions";
        }
        else{
            $linkId = $this->getLinkId($group);

            // Make our instrument name match the one REDCap will generate from the display name.
            $linkId = str_replace('.', '_', $linkId);
        }
     
        if($linkId){
            $name .= " ($linkId)";
        }

        if($displayName){
            return $name;
        }

        $name = strtolower($name);
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
            and message = ?
            $whereClause
            order by log_id desc
        ", RESOURCE_RECEIVED);
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

    private function createMeta($docType){
        return [
            'tag' => [
                [
                    'system' => 'https://clara.uams.edu/irb_num',
                    'code' => '220742', // TODO - Where does this value come from?
                    'display' => 'IRB Number'
                ],
                [
                    'system' => 'https://clara.uams.edu/doc_type',
                    'code' => $docType,
                    'display' => 'Document Type'
                ]
            ]
        ];
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
            'status' => 'completed',
            'meta' => self::createMeta($questionnaire->getMeta()->getTag()[0]->getCode())
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

                $answer = $this->createQuestionnaireAnswer($item, $value);
                $responseItem->addAnswer($answer);
            }
        });

        return $questionnaireResponse;
    }

    function createQuestionnaireAnswer($item, $value){
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
            $answerData = ['valueDateTime' => $this->formatFHIRDateTime(strtotime("$value UTC"))];
        }
        else if($type === 'date'){
            $answerData = ['valueDate' => $value];
        }
        else if($type === 'time'){
            $answerData = ['valueTime' => "$value:00"];
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

        return new FHIRQuestionnaireResponseAnswer($answerData);
    }

    function getRemoteFHIRServerUrl(){
        $url = $this->getProjectSetting('remote-fhir-server-url');
        if(empty($url)){
            throw new Exception('A remote FHIR server url must be configured.');
        }

        return $url;
    }

    function respondAndExit($o){
        header('Content-type: application/fhir+json');
        echo $this->jsonSerialize($o);
        exit();
    }

    function createQuestionnaireItem($redcapField){
        $fieldName = $redcapField['field_name'];

        $fhirType = $this->getFHIRType($redcapField);
        if($fhirType === null){
            throw new Exception("The type or validation for the following field is not currently supported: " . json_encode($redcapField, JSON_PRETTY_PRINT));
        }

        $item = [
            'linkId' => $fieldName,
            'text' => $redcapField['element_label'],
            'type' => $fhirType
        ];
    
        if($redcapField['field_req'] === 1){
            $item['required'] = true;
        }

        if($redcapField['element_type'] === 'descriptive'){
            $videoUrl = $redcapField['video_url'];
            if(!empty($videoUrl)){
                $item['text'] = $this->getDescriptiveVideoHTML($item['text'], $videoUrl);
            }
        }
    
        if($fhirType === 'choice'){
            $item['answerOption'] = $this->getFHIRAnswerOptions($redcapField);
        }

        $this->handleActionTags($redcapField, $item);

        return $item;
    }

    private function checkForUnsupportedActionTag($tagName){
        if(!in_array($tagName, SUPPORTED_ACTION_TAGS)){
            throw new Exception("The following action tag must be added to the supported tags constant: $tagName");
        }
    }

    function handleActionTags($redcapField, &$item){
        $getValue = function($tagName) use ($redcapField){
            $this->checkForUnsupportedActionTag($tagName);
            return @$redcapField['action_tags'][$tagName];
        };
        
        $isTagPresent = function($tagName) use ($redcapField){
            return $this->hasActionTag($redcapField, $tagName);
        };

        $default = $getValue('@DEFAULT');
        if($default){
            $item['initial'] = $this->getInitialValue($redcapField, $item, $default);
        }

        $charLimit = $getValue('@CHARLIMIT');
        if($charLimit){
            $item['maxLength'] = $charLimit;
        }

        $hiddenChoices = $getValue('@HIDECHOICE');
        if($hiddenChoices){
            $this->removeChoices($item, explode(',', $hiddenChoices));
        }

        if($isTagPresent('@READONLY')){
            $item['readOnly'] = true;
        }
    }

    private function removeChoices(&$item, $hiddenChoices){
        $hiddenChoices = array_flip($hiddenChoices);

        $newAnswerOptions = [];
        foreach($item['answerOption'] as $option){
            $code = $option['valueCoding']['code'];
            if(!isset($hiddenChoices[$code])){
                $newAnswerOptions[] = $option;
            }
        }

        $item['answerOption'] = $newAnswerOptions;
    }

    private function hasActionTag($redcapField, $tagName){
        $this->checkForUnsupportedActionTag($tagName);
        return isset($redcapField['action_tags'][$tagName]);
    }

    function parseActionTags($redcapField){
        $misc = $redcapField['misc'];
        $actionTags = explode(' ', $misc);

        $parsed = [];
        foreach($actionTags as $tag){
            if(strpos($tag, '@') !== 0){
                // We must be inside a quoted string value that contains spaces.  Skip this one.
                continue;
            }

            $parts = explode('=', $tag);
            $tagName = $parts[0];
            $parsed[$tagName] = Form::getValueInActionTag($misc, $tagName);
        }

        return $parsed;
    }

    private function getInitialValue($redcapField, $item, $value){
        $fhirType = $this->getFHIRType($redcapField);
        $methodNameSuffix = ucfirst($fhirType);
        
        if($fhirType === 'dateTime'){
            $value = $this->formatFHIRDateTime($value);
        }
        else if($fhirType === 'time'){
            $value = $this->formatFHIRTime($value);
        }
        else if(in_array($fhirType, ['text', 'textarea'])){
            $methodNameSuffix = 'String';
        }
        else if($fhirType === 'choice'){
            $value = $this->findCoding($item, $value);
            $methodNameSuffix = 'Coding';
        }

        return [
            'value' => [
                "value$methodNameSuffix" => $value
            ]
        ];
    }

    private function findCoding($item, $value){
        foreach($item['answerOption'] as $option){
            $coding = $option['valueCoding'];
            if($value === $coding['code']){
                return $coding;
            }
        }

        return null;
    }

    function getREDCapVersionDirURL(){
        return APP_PATH_WEBROOT_FULL . ltrim(APP_PATH_WEBROOT, '/');
    }

    function createQuestionnaire($pid, $formName, $formDisplayName, $fields, $repeatingForms){
        $repeatingForms = array_flip($repeatingForms);

        $questionnaire = new FHIRQuestionnaire([
            'name' => $formName,
            'title' => $formDisplayName,
            'status' => 'draft',
            'url' => $this->getREDCapVersionDirURL() . "Design/online_designer.php?pid=$pid&page=$formName"
        ]);

        $formGroup = new FHIRQuestionnaireItem([
            'linkId' => "form___$formName",
            'type' => 'group',
            'repeats' => isset($repeatingForms[$formName])
        ]);

        $questionnaire->addItem($formGroup);

        $skippedFields = [];
        $unsupportedActionTags = [];
        $group = $formGroup;
        foreach($fields as $field){
            $field['action_tags'] = $this->parseActionTags($field);
            $unsupportedActionTags = array_merge($unsupportedActionTags, $this->getUnsupportedActionTags($field));

            $fhirType = $this->getFHIRType($field);
            if($fhirType === null){
                $skippedFields[] = $field['field_name'];
                continue;
            }

            $sectionHeader = @$field['element_preceding_header'];
            if(!empty($sectionHeader)){
                $fakeSectionField = [
                    'field_name' => $field['field_name'] . "___section_header",
                    'element_label' => $sectionHeader,
                    'element_type' => FHIR_GROUP,
                ];

                $group = new FHIRQuestionnaireItem($this->createQuestionnaireItem($fakeSectionField));

                $formGroup->addItem($group);
            }

            if($this->hasActionTag($field, '@HIDDEN')){
                // Just exclude this field for now.
                // In the future we may want to implement the following extension instead:
                // https://www.hl7.org/fhir/extension-questionnaire-hidden.html
                continue;
            }

            $items = [];
            if($field['element_type'] === 'checkbox'){
                $choices = $this->parseREDCapChoices($field);
                foreach($choices as $key=>$value){
                    $item = $this->createQuestionnaireItem($field);
                    $item['linkId'] .= "___$key";
                    $item['text'] .= " - $value";
                    $items[] = $item;
                }
            }
            else{
                $items[] = $this->createQuestionnaireItem($field);;
            }

            foreach($items as $item){
                $group->addItem(new FHIRQuestionnaireItem($item));
            }
        }

        return [
            $questionnaire,
            [
                'skippedFields' => $skippedFields,
                'unsupportedActionTags' => array_keys($unsupportedActionTags)
            ]
        ];
    }

    private function getUnsupportedActionTags($redcapField){
        $unsupportedActionTags = [];
        foreach($redcapField['action_tags'] as $tagName=>$value){
            if(!in_array($tagName, SUPPORTED_ACTION_TAGS)){
                $unsupportedActionTags[$tagName] = true;
            }
        }

        return $unsupportedActionTags;
    }

    function getDescriptiveVideoHTML($text, $url){
        $url = $this->getVideoEmbedURL($url);
        return "
            <p>$text</p>
            <div style='max-width: 600px'   >
                <div style='
                    position: relative;
                    padding-bottom: 56.25%; /* 16:9 */
                    height: 0;
                '>
                    <iframe 
                        style='
                            position: absolute;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                        '
                        src='$url'>
                    </iframe>
                </div>
            </div>
        ";
    }

    // The contents of this function were copied from REDCap core.
    private function getVideoEmbedURL($video_url){
       // Default values
        $unknown_video_service = '1';
        $video_url_formatted = $video_url;
        // Vimeo URL
        if (stripos($video_url, 'vimeo.com') !== false
            && preg_match("/https?:\/\/(?:www\.|player\.)?vimeo.com\/(?:channels\/(?:\w+\/)?|groups\/([^\/]*)\/videos\/|album\/(\d+)\/video\/|video\/|)(\d+)(?:$|\/|\?)/", $video_url, $matches)) {
            $unknown_video_service = '0';
            $video_url_formatted = 'https://player.vimeo.com/video/' . $matches[3];
        }
        // Youtube URL
        elseif ((stripos($video_url, 'youtube.com') !== false || stripos($video_url, 'youtu.be') !== false)
                && preg_match("/\s*[a-zA-Z\/\/:\.]*youtu(be.com\/watch\?v=|.be\/)([a-zA-Z0-9\-_]+)([a-zA-Z0-9\/\*\-\_\?\&\;\%\=\.]*)/i", $video_url, $matches)) {
            $unknown_video_service = '0';
            $video_url_formatted = 'https://www.youtube.com/embed/' . $matches[2] . '?wmode=transparent&rel=0';
        }
        return $video_url_formatted;
    }

    function getMappedFieldsAsBundle($projectId, $recordId){
        $m = new FieldMapper($this, $projectId, $recordId);
        return $this->createBundle($m->getResources());
    }

    private function createResource($type, $args){
        return array_merge([
            'resourceType' => $type,
        ], $args);
    }

    function createBundle($resources, $type = 'collection'){
        $bundle = $this->createResource('Bundle', [
            'type' => $type,
        ]);

        foreach($resources as $resource){
            $bundle['entry'][] = [
                'fullUrl' => $this->getResourceUrl($resource),
                'resource' => $resource
            ];
        }

        return $bundle;
    }

    function formatConsentCategories($values){
        $categories = [];
        foreach($values as $value){
            if(ctype_digit($value[0])){
                $system = 'http://loinc.org';

            }
            elseif(ctype_upper($value)){
                $system = 'http://terminology.hl7.org/CodeSystem/v3-ActCode';
            }
            else{
                $system = 'http://terminology.hl7.org/CodeSystem/consentcategorycodes';
            }

            $categories[] = [
                'coding' => [
                    [
                        'system' => $system,
                        'code' => $value
                    ]
                ]
            ];
        }

        return $categories;
    }

    function getEConsentFHIRBundle($args){
        $type = $args['type'];
        $version = $args['version'];
        $firstName = $args['firstName'];
        $lastName = $args['lastName'];
        $patientId = $args['patientId'];

        $patient = $this->createResource('Patient', [
            'id' => $patientId,
            'name' => [
                [
                    'given' => [
                        $firstName
                    ],
                    'family' => $lastName
                ]
            ],
            'birthDate' => $args['birthDate']
        ]);

        $consent = $this->createResource('Consent', [
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
                'title' => "$type eConsent Version $version for $firstName $lastName",
            ],
            'policy' => [
                [
                    'authority' => $args['authority']
                ]
            ]
        ]);

        return $this->createBundle([$consent, $patient]);
    }

    function validateInBrowserAndDisplay($resource){
        ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/ajv/6.12.6/ajv.min.js" integrity="sha512-+WCxUYg8L1mFBIyL05WJAJRP2UWCy+6mvpMHQbjPDdlDVcgS4XYyPhw5TVvzf9Dq8DTD/BedPW5745dqUeIP5g==" crossorigin="anonymous"></script>
        <script>
            (function(){
                var handleError = function(error, resource){
                    document.write(
                        '<h4>FHIR Validation Error - Please report this error along with instructions on how to reproduce it.</h4>' +
                        '<label>Errors</label><pre>' + error + '</pre>' +
                        '<br><label>Resource</label><pre>' + JSON.stringify(resource, null, 2) + '</pre>'
                    )
                }

                var draft06 = <?=file_get_contents(__DIR__ . '/fhir/json-schema-draft-06.json')?>;
                var schema = <?=SchemaParser::getSchemaJSON()?>;
                var bundle = <?=json_encode($resource, JSON_PRETTY_PRINT)?>;

                try{
                    // AJV throws a bunch of warnings if I don't remove these (likely because the FHIR schema is not quite valid).
                    schema.$schema = undefined
                    schema.id = undefined
                    
                    for(var entryIndex in bundle.entry){
                        var resource = bundle.entry[entryIndex].resource

                        schema.oneOf = [
                            {
                                '$ref': '#/definitions/' + resource.resourceType
                            }
                        ]
        
                        var ajv = new Ajv()

                        /**
                         * Added per the recommendation here:
                         * https://github.com/ajv-validator/ajv/tree/v6.12.6#using-version-6
                         */
                        ajv.addMetaSchema(draft06)

                        var validate = ajv.compile(schema)
                        if(!validate(resource)){
                            handleError(JSON.stringify(validate.errors, null, 2), resource)
                            return
                        }
                    }

                    // We'll comment out downloads for now, since they're a little annoying to test with.
                    // var a = document.createElement('a')
                    // var blob = new Blob([JSON.stringify(bundle, null, 2)]) // , {type: mimeType})
                    // var url = URL.createObjectURL(blob)
                    // a.setAttribute('href', url)
                    // a.setAttribute('download', 'Bundle-' + (new Date()).toJSON() + '.json')
                    // a.click()
                    // close()

                    document.write('<pre>' + JSON.stringify(bundle, null, 2) + '</pre>')
                }
                catch(error){
                    handleError(error, bundle)
                }
            })()
        </script>
        <?php
    }

    function getEConsentFormSettings($formName){
        $allFormSettings = $this->getSubSettings('econsent-form-settings');
        foreach($allFormSettings as $formSettings){
            if($formSettings['form-name'] === $formName){
                return $formSettings;
            }
        }

        throw new \Exception('Modules settings must be entered before eConsents can be exported for the following form: ' . $formName);
    }

    // This method is based on Survey::getEconsentOptionsData() in REDCap Core.
    function getEConsentData($form, $record, $instance){
        $Proj = new \Project();
        $surveySettings = $Proj->surveys[$Proj->forms[$form]['survey_id']];
        
        $fields = [];
        $events = [];
        $allFieldDetails = [];
        foreach(['firstname', 'lastname', 'dob'] as $fieldType){
            $eventSettingName = "pdf_econsent_{$fieldType}_event_id";
            $fieldSettingName = "pdf_econsent_{$fieldType}_field";

            $event = $surveySettings[$eventSettingName];
            $field = $surveySettings[$fieldSettingName];

            if(empty($field)){
                continue;
            }
            
            $events[] = $event;
            $fields[] = $field;
            
            $allFieldDetails[$fieldType] = [
                'event' => $event,
                'field' => $field,
            ];
        }

        $data = \Records::getData($Proj->project_id, 'array', $record, $fields, $events);

        $returnData = [
            'type' => $surveySettings['pdf_econsent_type'],
            'version' => $surveySettings['pdf_econsent_version']
        ];
        
        foreach($allFieldDetails as $fieldType => $details){
            $field = $details['field'];

            $event = $details['event'];
            if(!isset($event)){
                $event = $Proj->firstEventId;
            }

            $thisFieldForm = $Proj->metadata[$fields[0]]['form_name'];
            $thisFieldRepeating = $Proj->isRepeatingFormOrEvent($events[0], $thisFieldForm);
            $thisFieldRepeatInstrument = "";
            if ($thisFieldRepeating) {
                $thisFieldRepeatInstrument = $Proj->isRepeatingForm($surveySettings[$event], $thisFieldForm) ? $thisFieldForm : "";
            }

            if ($thisFieldRepeating) {
                $value = $data[$record]['repeat_instances'][$event][$thisFieldRepeatInstrument][$instance][$field];
            } else {
                $value =  $data[$record][$event][$field];
            }

            $returnData[$fieldType] = $value;
        }

        return $returnData;
    }

    function getProjectHomeUrl($pid){
        return APP_PATH_WEBROOT_FULL . ltrim(APP_PATH_WEBROOT, '/') . "?pid=$pid";
    }

    // This can be removed once it makes it into a framework version.
    function getSurveyResponseDetails($form, $recordId, $eventId = null, $instance = null) {
        $pid = $this->getProjectId();

        $query = $this->createQuery();
		$query->add("
			select p.*, r.*
            from redcap_surveys s
            join redcap_surveys_participants p
                on p.survey_id = s.survey_id
            join redcap_surveys_response r
                on r.participant_id = p.participant_id 
            where
                project_id = ?
                and form_name = ?
                and r.record = ?
		", [$pid, $form, $recordId]);

		if($eventId !== null){
			$query->add(" and p.event_id = ?", $eventId);
		}

        if($instance !== null){
			$query->add(" and r.instance = ?", $instance);
		}

        // This table sometimes contains duplicate entries.
        // Mark isn't sure why, but the following seems to weed almost all of them out.
        // It might be appropriate to throw an exception for any that remain,
        // since it could be considered a data corruption issue on the project.
        $query->add('
            and completion_time is not null
            order by completion_time asc
        ');

		$result = $query->execute();
		$row = $result->fetch_assoc();
        if($result->fetch_assoc() !== null){
            throw new Exception("Multiple survey responses found for pid '$pid', record '$recordId', event '$eventId', and instance '$instance'!  You may need to supply more arguments to target the response you're looking for.");
        }

        return $row;
	}
}