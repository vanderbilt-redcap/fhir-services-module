<?php

// TODO - The iREX id will likely be different than the one in REDCap.
$studyId = $study->getId();

?>

<div xmlns="http://www.w3.org/1999/xhtml">
    <p style="font-style: italic;">This is a Trial Innovation Network study.</p>
    <p>Information about this study is available online at: 
        
        <br/>
        <a href="https://www.irbexchange.org/study/index/?proj=<?=$studyId?>">https://www.irbexchange.org/study/index/?proj=<?=$studyId?></a>
    </p>
    <p>Sincerely,</p>
    <p>The IREx Team</p>
</div>