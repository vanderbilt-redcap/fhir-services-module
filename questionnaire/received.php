<?php namespace Vanderbilt\FHIRServicesExternalModule;

use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRQuestionnaire;


$result = $module->getReceivedQuestionnaires();
$rows = [];
while($row = $result->fetch_assoc()){
    $row['object'] = $module->parse($row['content']);
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
</style>

<div class="projhdr">Questionnaire Data Dictionary Options</div>
<br>
<h5>Received Questionnaires</h5>
<table class='table recevied-questionnaires'>
    <tr>
        <th>ID</th>
        <th>Date/Time</th>
        <th>Title</th>
        <th></th>
    </tr>
    <?php
    foreach($rows as $logId=>$row){
        ?>
        <tr data-log-id="<?=$logId?>">
            <td><?=$logId?></td>
            <td><?=$row['timestamp']?></td>
            <td><?=$row['object']->getTitle()?></td>
            <td>
                <button class='details'>Show Details</button>
                <button class='replace-data-dictionary'>Replace Data Dictionary</button>
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

        table.find('button.details').click(function(){
            var logId = getLogId(this)

            var width = window.innerWidth - 100;
            var height = window.innerHeight - 200;
            var content = '<pre style="max-height: ' + height + 'px">' + data[logId]['content'] + '</pre>'

            simpleDialog(content, 'Details', null, width)
        })
        
        table.find('button.replace-data-dictionary').click(function(){
            var logId = getLogId(this)

            var form = $("<form style='display: none'><input name='log-id' value='" + logId + "'></form>")[0]
            form.method = 'POST'
            form.action = <?=json_encode($module->getQuestionnaireUrl('data-dictionary'))?>;

            $('body').append(form)
            
            form.submit()  
        })
    })
</script>