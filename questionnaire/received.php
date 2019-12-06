<?php namespace Vanderbilt\FHIRServicesExternalModule;

use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRQuestionnaire;


$result = $module->getReceivedResources();
$rows = [];
while($row = $result->fetch_assoc()){
    $o = $module->parse($row['content']);;
    
    $type = $row['type'];
    if($type === 'Questionnaire'){
        $subType = $o->getTitle();
    }
    else if($type === 'QuestionnaireResponse'){
        if($o->getMeta() && !empty($o->getMeta()->getTag())){
            $subType = $o->getMeta()->getTag()[0]->getCode();
        }
        else{
            $subType = $module->getText($o->getItem()[0]);
        }
    }
    else if($type === 'Binary'){
        $contentType = $module->getValue($o->getContentType());
        $extension = $module->getExtensionForMIMEType($contentType);
      
        if($extension){
            $subType = strtoupper($extension);
            $row['download'] = true;
        }
        else{
            $subType = 'Unknown Content Type: ' . $contentType;
        }
    }
    else{
        $subType = '';
    }

    $row['subType'] = $subType;

    $rows[$row['log_id']] = $row;
}

?>

<style>
    table.recevied-questionnaires{
        max-width: 900px;
    }

    table.recevied-questionnaires th{
        font-weight: bold;
    }

    table.recevied-questionnaires button{
        margin-bottom: 3px;
    }
</style>

<div class="projhdr">Received Resources</div>
<table class='table recevied-questionnaires'>
    <tr>
        <th>ID</th>
        <th>Date/Time</th>
        <th>Resource Type</th>
        <th>Sub-Type</th>
        <th style='min-width: 300px'></th>
    </tr>
    <?php
    foreach($rows as $logId=>$row){
        $type = $row['type'];
        ?>
        <tr data-log-id="<?=$logId?>">
            <td><?=$logId?></td>
            <td><?=$row['timestamp']?></td>
            <td><?=$type?></td>
            <td><?=$row['subType']?></td>
            <td>
                <button class='download'>Download</button>
                <?php
                if($type === 'Questionnaire') {
                    ?><button class='replace-data-dictionary'>Replace Data Dictionary</button><?php
                }
                else if($type === 'QuestionnaireResponse') {
                    ?><button class='import-record'>Import as Record</button><?php
                }
                else if($type === 'Binary') {
                    ?><button class='binary-download'>Download Binary File</button><?php
                }
                ?>
            </td>
        </tr>
        <?php
    }   
    ?>
</table>


<script>
    $(function(){
        var data = <?=json_encode($rows)?>;
        var table = $('table.recevied-questionnaires')
        
        var getLogId = function(element){
            return $(element).closest('tr').data('log-id')
        }
        
        table.find('button.replace-data-dictionary').click(function(){
            var logId = getLogId(this)

            var form = $("<form style='display: none'><input name='log-id' value='" + logId + "'></form>")[0]
            form.method = 'POST'
            form.action = <?=json_encode($module->getQuestionnaireUrl('data-dictionary'))?>;

            $('body').append(form)
            
            form.submit()  
        })

        table.find('button.import-record').click(function(){
            window.open(<?=json_encode($module->getUrl('questionnaire/import-response.php') . '&log-id=')?> + getLogId(this))
        })

        var download = function(button, additionalParameters){
            window.open(<?=json_encode($module->getUrl('download-resource.php') . '&log-id=')?> + getLogId(button) + '&' + additionalParameters)
        }

        table.find('button.download').click(function(){
            download(this, '')
        })

        table.find('button.binary-download').click(function(){
            download(this, 'binary')
        })
    })
</script>