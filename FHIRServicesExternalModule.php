<?php namespace Vanderbilt\FHIRServicesExternalModule;

class FHIRServicesExternalModule extends \ExternalModules\AbstractExternalModule{
    function redcap_data_entry_form($project_id, $record){
        if(\REDCap::getRecordIdField() === 'composition_id'){
            ?>
            <script>
                (function(){
                    var pdfButton = $('#pdfExportDropdownTrigger')
                    var bundleButton = $('<a href="<?=$this->getUrl('service.php', true) . "&fhir-url=/Composition/$record/\$document"?>">Create FHIR Bundle</a>')
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
}