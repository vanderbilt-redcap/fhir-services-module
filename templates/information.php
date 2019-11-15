<?php

$sponsor = $getResourceFromReference($study->getSponsor());

$pi = $getResourceFromReference($piRole->getPractitioner());
$piName = $pi->getName()[0];
?>

<div xmlns="http://www.w3.org/1999/xhtml">
    <p>This letter serves as documentation that 
        
        <strong><?=$relyingOrg->getName()?></strong> has agreed to rely on the
        <strong><?=$sponsor->getName()?></strong> IRB using the SMART IRB Master Common Reciprocal Institutional Review Board Authorization Agreement.
    </p>
    <p>
        <strong>NOTE: This is not a notice of IRB approval. A separate email will be sent when the study is approved by the reviewing IRB.</strong>
    </p>
    <p>
        <strong>Study Title:</strong> <?=$study->getTitle()?>
    </p>
    <p>
        <strong>Relying Site PI:</strong> <?=implode(' ', $piName->getGiven())?> <?=$piName->getFamily()?>
    </p>
</div>