<?php
$section = @$_GET['section'];
if($section){
    require_once $module->getSafePath("questionnaire/$section.php");
    return;
}
?>

<div class="projhdr">Questionnaire Options</div>

<p><a href="<?=$module->getUrl('questionnaire-options.php?section=data-dictionary')?>"><button>Data Dictionary Options</button></a></p>

<form id='fhir-import-questionnaire-response-form' method='post' action='<?=$module->getUrl('questionnaire/import-response.php')?>' enctype='multipart/form-data'>
    <button>Import a FHIR QuestionnaireResponse</button>
    <input type='file' name='import' style='display: none'>
</form>
<script>
    $(function(){
        var form = $('#fhir-import-questionnaire-response-form')
        var input = form.find('input')

        input.change(function(){
            form.submit()
        })

        form.find('button').click(function(e){
            e.preventDefault()
            input.click()
        })
    })
</script>