<?php namespace Vanderbilt\FHIRServicesExternalModule;

require_once __DIR__ . '/FHIRUtil.php';

use MetaData;

class FHIRServicesExternalModule extends \ExternalModules\AbstractExternalModule{
    function redcap_data_entry_form($project_id, $record){
        if($this->getProjectSetting('project-type') === 'composition'){
            $projectId = $this->getProjectId();
            $urlPrefix = $this->getUrl('service.php', true);
            $urlPrefix = str_replace("&pid=$projectId", '', $urlPrefix);    
            ?>
            <script>
                (function(){
                    var pdfButton = $('#pdfExportDropdownTrigger')
                    var bundleButton = $('<a href="<?="$urlPrefix&fhir-url=/Composition/$projectId-$record/\$document"?>">Create FHIR Bundle</a>')
                    bundleButton.attr('class', pdfButton.attr('class'))
                    bundleButton.css({
                        'margin-left': '3px',
                        'min-height': '26px',
                        'margin-bottom': '15px',
                        'vertical-align': 'top'
                    })
    
                    pdfButton.after(bundleButton)
                })()
            </script>
            <?php
        }
    }

    function redcap_every_page_top(){
        if(strpos($_SERVER['REQUEST_URI'], APP_PATH_WEBROOT . 'Design/data_dictionary_upload.php') === 0){
            //$this->onDataDictionaryUploadPage();
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
            strpos($link['url'], 'questionnaire-settings') !== false
            &&
            $this->getProjectSetting('project-type') !== 'questionnaire'
        ){
            return false;
        }
        
        return $link;
    }

    // This method was mostly copied from data_dictionary_upload.php
    function saveQuestionnaire($file){
        $dictionaryPath = tempnam(sys_get_temp_dir(), 'fhir-questionnaire-data-dictionary-');
        
        try{
            file_put_contents($dictionaryPath, FHIRUtil::questionnaireToDataDictionary($file['tmp_name']));

            require_once APP_PATH_DOCROOT . '/Design/functions.php';
            $dictionary_array = excel_to_array($dictionaryPath);
        }
        finally{
            unlink($dictionaryPath);
        }

        list ($errors_array, $warnings_array, $dictionary_array) = MetaData::error_checking($dictionary_array);

        $handleErrors = function($errors, $type){
            if(empty($errors)){
                return false;
            }

            ?>
            <p>Uploading the questionnaire failed with the following <?=$type?>:</p>
            <pre><?=json_encode($errors)?></pre>
            <br>
            <?php
            
            return true;
        };

        if($handleErrors($errors_array, 'errors')){
            return;
        }

        if($handleErrors($warnings_array, 'warnings')){
            return;
        }

        // Set up all actions as a transaction to ensure everything is done here
        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");
        
        // Create a data dictionary snapshot of the *current* metadata and store the file in the edocs table
        MetaData::createDataDictionarySnapshot();

        // Save data dictionary in metadata table
        $sql_errors = MetaData::save_metadata($dictionary_array);

        // Display any failed queries to Super Users, but only give minimal info of error to regular users
        if ($handleErrors($sql_errors, 'errors')) {
            // ERRORS OCCURRED, so undo any changes made
            db_query("ROLLBACK");
            // Set back to previous value
            db_query("SET AUTOCOMMIT=1");

            return;
        }

        // COMMIT CHANGES
        db_query("COMMIT");
        // Set back to previous value
        db_query("SET AUTOCOMMIT=1");

        $edocId = \Files::uploadFile($file, $this->getProjectId());
        $this->setProjectSetting('questionnaire', $edocId);
    }

    function getQuestionnaireEDoc(){
        $edocId = $this->getProjectSetting('questionnaire');
        if(!$edocId){
            return null;
        }

        $edocId = db_escape($edocId);
        $result = $this->query("select * from redcap_edocs_metadata where doc_id = $edocId");
        return $result->fetch_assoc();
    }
}