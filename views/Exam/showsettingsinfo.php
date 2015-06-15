<?php
/**
 * File: showsettingsinfo.php
 * User: Masterplan
 * Date: 4/23/13
 * Time: 5:54 PM
 * Desc: Shows test setting's info or shows new empty panel to add new test setting
 */

global $user, $tt, $log;

$YNlabel[0] = 'No';
$YNlabel[1] = 'Yes';

$editClass = 'writable';
$name = ttNewTestSettings;
$scoreType = '10';
$scoreMin = '0';
$bonus = '0';
$negative = '0';
$editable = '0';
$duration = '90';
$desc = '';
$summaryClass = '';

$topics = array();
$questionsForTopic = array();
$mandatoryQuestions = array();

$db = new sqlDB();
if($_POST['action'] == 'show'){
    if(($db->qSelect('TestSettings', 'idTestSetting', $_POST['idTestSetting'])) && ($testSettings = $db->nextRowAssoc())){

        $editClass = 'readonly';
        $name = $testSettings['name'];
        $scoreType = $testSettings['scoreType'];
        $scoreMin = $testSettings['scoreMin'];
        $bonus = $testSettings['bonus'];
        $negative = $testSettings['negative'];
        $editable = $testSettings['editable'];
        $duration = $testSettings['duration'];
        $desc = $testSettings['description'];
        $summaryClass = 'hidden';

        if($db->qSelect('Topics_TestSettings', 'fkTestSetting', $_POST['idTestSetting'])){
            $questionsForTopic = $db->getResultAssoc('fkTopic');
        }else{
            die($db->getError());
        }
    }else{
        die($db->getError());
    }
}
?>

<form class="infoEdit" onsubmit="return false;">
    <div class="columnLeft">
        <h2 class="center"><?= ttGeneralInformations ?></h2>

        <label><?= ttName ?> : </label>
        <input class="<?= $editClass ?>" type="text" id="settingsName" size="50" value="<?= $name ?>">
        <a id="settingsNameChars" class="charsCounter hidden"></a>

        <label class="tSpace"><?= ttScoreType ?> : </label>
        <dl class="dropdownInfo tSpace" id="settingsScoreType">
            <dt class="<?= $editClass ?>">
                <span><?= constant('ttST'.$scoreType) ?><span class="value"><?= $scoreType ?></span></span>
            </dt>
            <dd>
                <ol>
    <?php
    $scoreTypes = getScoreTypes();
    $index = 0;
    while($index < count($scoreTypes)){
        $type = 'ttST'.$scoreTypes[$index];
        echo '<li>'.constant($type).'<span class="value" value="">'.$scoreTypes[$index].'</span></li>';
        $index++;
    }
    ?>
                </ol>
            </dd>
        </dl>
        <div class="clearer"></div>

        <label class="tSpace"><?= ttScoreMin ?> : </label>
        <input class="left <?= $editClass ?> tSpace numeric" type="number" min="0" id="settingsScoreMin" value="<?= $scoreMin ?>">

        <label class="tSpace"><?= ttBonus ?> : </label>
        <input class="left <?= $editClass ?> tSpace numeric" type="number" min="0" id="settingsBonus" value="<?= $bonus ?>">

        <div class="clearer"></div>

        <label class="tSpace"><?= ttNegativeScores ?> : </label>
        <dl class="dropdownInfo tSpace" id="settingsNegative">
            <dt class="<?= $editClass ?>">
                <span><?= constant('tt'.$YNlabel[$negative]) ?><span class="value"><?= $negative ?></span></span>
            </dt>
            <dd>
                <ol>
                    <li><?= ttNo ?><span class="value">0</span></li>
                    <li><?= ttYes ?><span class="value">1</span></li>
                </ol>
            </dd>
        </dl>

        <label id="settingsEditableLabel" class="tSpace"><?= ttEditableScore ?> : </label>
        <dl class="dropdownInfo tSpace" id="settingsEditable">
            <dt class="<?= $editClass ?>">
                <span><?= constant('tt'.$YNlabel[$editable]) ?><span class="value"><?= $editable ?></span></span>
            </dt>
            <dd>
                <ol>
                    <li><?= ttNo ?><span class="value">0</span></li>
                    <li><?= ttYes ?><span class="value">1</span></li>
                </ol>
            </dd>
        </dl>

        <div class="clearer"></div>

        <label class="tSpace"><?= ttDuration ?> : </label>
        <div id="settingsDuration" class="tSpace">
            <dl class="dropdownInfo" id="settingsDurationH">
                <dt class="<?= $editClass ?>">
                    <span><?= intval($duration/60) ?>
                        <span class="value"><?= intval($duration/60) ?></span>
                    </span>
                </dt>
                <dd>
                    <ol>
                        <?php
                        for($hour = 0; $hour <= 10; $hour++)
                            echo '<li>'.$hour.'<span class="value">'.$hour.'</span></li>';
                        ?>
                    </ol>
                </dd>
            </dl>
            <label><?= ttHours ?></label>
            <dl class="dropdownInfo" id="settingsDurationM">
                <dt class="<?= $editClass ?>">
                    <span><?= sprintf("%02u", intval($duration%60)) ?>
                        <span class="value"><?= intval($duration%60) ?></span>
                    </span>
                </dt>
                <dd>
                    <ol>
                        <?php
                        for($minutes = 0; $minutes <= 55; $minutes = $minutes+5){
                            echo sprintf("<li>%02u<span class=\"value\">%u</span></li>", $minutes, $minutes);
                        }
                        ?>
                    </ol>
                </dd>
            </dl>
            <label><?= ttMinutes ?></label>
            <div class="clearer"></div>
        </div>
        <div class="clearer"></div>

        <label class="tSpace"><?= ttDescription ?> : </label>
        <textarea class="<?= $editClass ?> tSpace" id="settingsDesc"><?= $desc ?></textarea>
        <a id="settingsDescChars" class="charsCounter hidden"></a>
        <div class="clearer"></div>
    </div>

<?php

/*
 * questionDistribution[idTopic][idDifficulty] = [#random , #mandatory]
 */

$questionsDistribution = array();
$difficulties = array(
    1 => "Easy",
    2 => "Medium",
    3 => "Hard"
);

// Read random question for topic and difficulty
if(($db->qSelect('Topics', 'fkSubject', $_SESSION['idSubject'])) && ($topics = $db->getResultAssoc('idTopic'))){
    foreach($topics as $topic){
        foreach(range(1, count($difficulties)) as $indexDifficulty){
            $random = (isset($questionsForTopic[$topic['idTopic']]))? $questionsForTopic[$topic['idTopic']]['num'.$difficulties[$indexDifficulty]] : 0;
            $questionsDistribution[$topic['idTopic']][$indexDifficulty] = array($random ,0);
        }
    }
}

// Read mandatory question for topic and difficulty
if(($_POST['action'] == 'show') && ($db->qMandatoryQuestions($_POST['idTestSetting'])) && ($db->numResultRows()) > 0){
    while($mandatory = $db->nextRowAssoc()){
        array_push($mandatoryQuestions, "".$mandatory['idQuestion']);
        $questionsDistribution[$mandatory['fkTopic']][$mandatory['difficulty']][1]++;
    }
}

?>

    <div class="columnRight">
        <h2 class="center"><?= ttQuestionsDistribution ?> (<?= ttRandom ?> + <?= ttMandatory ?>)</h2>
        <div id="questionsDistributionContainer" class="bSpace">
            <table id="questionsDistribution" class="cell-border">
                <thead>
                    <tr>
                        <th class="dTopic"><?= ttTopics ?></th>
                        <?php
                        foreach(range(1, 3) as $indexDifficulty)
                            echo "<th class='d".constant('ttD'.$indexDifficulty)." difficultyTitle'>".constant('ttD'.$indexDifficulty)."</th>";
                        ?>
                        <th class="dTot"><?= ttTotals ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                foreach($topics as $topic){
                    echo "<tr><td class='topicName bold'>".$topic['name']."</td>";
                    foreach(range(1, 3) as $indexDifficulty){
                    ?>
                        <td class="topic<?= $topic['idTopic'] ?> difficulty<?= $indexDifficulty ?>">
                            <input id="r-<?= $topic['idTopic'] ?>-<?= $indexDifficulty ?>" class="questionsRandom <?= $editClass ?> numeric" min="0"
                                   value="<?= $questionsDistribution[$topic['idTopic']][$indexDifficulty][0] ?>"
                                   onchange="changeTopicQuestions(<?= $topic['idTopic'] ?>, <?= $indexDifficulty ?>);"
                                   onkeyup="changeTopicQuestions(<?= $topic['idTopic'] ?>, <?= $indexDifficulty ?>);">
                            <span class="questionsMandatory">
                                <?php
                                if($questionsDistribution[$topic['idTopic']][$indexDifficulty][1] > 0)
                                    echo "+".$questionsDistribution[$topic['idTopic']][$indexDifficulty][1];
                                else
                                    echo "&nbsp;&nbsp;&nbsp;";
                                ?>
                            </span>
                        </td>
                    <?php
                    }
                    echo "<td id='qTopic".$topic['idTopic']."_tot'>
                              <span class='questionsRandomTot'></span><span class='questionsMandatoryTot'></span><span class='questionsTot'></span>
                          </td>
                      </tr>";
                }
                ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td></td>
                    <?php
                    foreach(range(1, 3) as $indexDifficulty){ ?>
                        <td id='qDifficulty<?= $indexDifficulty ?>_tot'>
                            <span class='questionsRandomTot'></span><span class='questionsMandatoryTot'></span><span class='questionsTot'></span>
                        </td>
                    <?php
                    }
                    ?>
                        <td id="questionsTot">
                            <span class="questionsRandomTot"></span><span class="questionsMandatoryTot"></span><span class="questionsTot underline"></span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="clearer"></div>
    </div>

    <script>
        var oldSelectedQuestion = new Array('<?php echo implode("','", $mandatoryQuestions) ?>');
        var questionsDistribution = <?php echo json_encode($questionsDistribution) ?>;
    </script>

    <div class="clearer"></div>
</form>