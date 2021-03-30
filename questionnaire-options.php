<?php
$section = @$_GET['section'];
if($section){
    require_once $module->framework->getSafePath("questionnaire/$section.php");
    return;
}

$getSectionButton = function($section, $label) use ($module){
    $url = $module->getQuestionnaireUrl($section);
    return "
        <p>
            <a href='$url'>
                <button>$label</button>
            </a>
        </p>
    ";
};

?>

<div class="projhdr">Questionnaire Options</div>

<?=$getSectionButton('data-dictionary', 'Data Dictionary Options')?>

<form id='fhir-import-questionnaire-response-form' method='post' action='<?=$module->getQuestionnaireUrl('import-response')?>' enctype='multipart/form-data'>
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