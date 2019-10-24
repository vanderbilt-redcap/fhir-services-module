<?php namespace Vanderbilt\FHIRServicesExternalModule;

class FHIRServicesExternalModule extends \ExternalModules\AbstractExternalModule{
    function redcap_data_entry_form($project_id, $record){
        ?>
        <script>
            (function(){
                var pdfButton = $('#pdfExportDropdownTrigger')
                var bundleButton = $('<a href="<?=$this->getUrl('service.php') . "&fhir-url=/Composition/$record/\$document"?>">Create FHIR Bundle</a>')
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