/**
 * File: Showsettingsinfo.js
 * User: Masterplan
 * Date: 27/05/14
 * Time: 13:24
 * Desc: Shows test setting's info and allows to edit its informations
 */

/*
 * questionsDistribution[idTopic][idDifficulty] = [#random , #mandatory]
 */

$(function(){

    $(".readonly").attr("disabled", "");

    /**
     *  @descr  Enables test settings info dropdown
     */
    $(".dropdownInfo dt.writable").on("click", function() {
                                                    $(this).children("span").toggleClass("clicked");
                                                    $(this).next().children("ol").slideToggle(200);
                                                });
    $(".dropdownInfo dd ol li").on("click", function() {
                                                updateDropdown($(this));
                                            });
    $(document).on('click', function(e) {
        var $clicked = $(e.target);
        if(!($clicked.parents().hasClass("dropdownInfo"))){
            $(".dropdownInfo dd ol").slideUp(200);
            $(".dropdownInfo dt span").removeClass("clicked");
        }
    });

    /**
     *  @descr  Enables chars counters for settingsName and settingsDesc fields
     */
    enableCharsCounter("settingsName", "settingsName");
    enableCharsCounter("settingsDesc", "settingsDesc");

    distributionTable = $("#questionsDistribution").DataTable({
                                                        scrollY:        163,
                                                        scrollCollapse: false,
                                                        searching:      false,
                                                        ordering:       false,
                                                        paging:         false,
                                                        sDom:           "t",
                                                        columns : [
                                                            { className: "dTopic"},
                                                            { className: "dEasy", width : "13%" },
                                                            { className: "dMedium", width : "13%" },
                                                            { className: "dHard", width : "13%" },
                                                            { className: "dTot", width : "14%" }
                                                        ]})
});

/**
 *  @name   selectQuestion
 *  @descr  Selects/Deselects single mandatory question and checks its topic and difficulty values to specified in topic/difficulty sections
 *  @param  selectedQuestion        DOM Element         Selected question's checkbox
 */
function selectQuestion(selectedQuestion){
    if(settingsEditing){
        var selectedQuestionRow = questionsTable.row(questionsTable.cell($(selectedQuestion).parent()).index().row).data();
        var topicID = selectedQuestionRow[qtci.topicID];
        var difficultyLevel = selectedQuestionRow[qtci.difficultyID];

        console.log("topicID", topicID);
        console.log("difficultyLevel", difficultyLevel);
        console.log(questionsDistribution[topicID][difficultyLevel]);

        if($(selectedQuestion).is(":checked")){
            questionsDistribution[topicID][difficultyLevel][1]++;
        }else{
            questionsDistribution[topicID][difficultyLevel][1]--;
        }
        updateQuestionsSummaries();
    }
}

/**
 *  @name   updateQuestionsSummaries
 *  @descr  Calculates new questions summaries for topics and difficulties sections and updates summary boxes and question field
 */
function updateQuestionsSummaries(){

    var difficultiesRandom = Array.apply(null, new Array(maxDifficulty+1)).map(Number.prototype.valueOf,0);
    var difficultiesMandatory = Array.apply(null, new Array(maxDifficulty+1)).map(Number.prototype.valueOf,0);

    $.each(questionsDistribution, function(topicID, questionsPerDifficulty){
        var topicRandomTemp = 0;
        var topicMandatoryTemp = 0;
        $.each(questionsPerDifficulty, function(difficultyID, questions){
            difficultiesRandom[difficultyID] += parseInt(questions[0]);
            difficultiesMandatory[difficultyID] += parseInt(questions[1]);
            topicRandomTemp += parseInt(questions[0]);
            topicMandatoryTemp += parseInt(questions[1]);
            if(questions[1] > 0)
                $("td.topic"+topicID+".difficulty"+difficultyID+" .questionsMandatory").text("+"+questions[1]);
            else
                $("td.topic"+topicID+".difficulty"+difficultyID+" .questionsMandatory").html("&nbsp;&nbsp;&nbsp;");
        });

        if(topicMandatoryTemp > 0){
            $("#qTopic"+topicID+"_tot .questionsRandomTot").text(topicRandomTemp);
            $("#qTopic"+topicID+"_tot .questionsMandatoryTot").text("+"+topicMandatoryTemp+"=");
        }
        $("#qTopic"+topicID+"_tot .questionsTot").text(topicRandomTemp+topicMandatoryTemp);
    });

    // Update difficulties totals
    var randomTot = 0;
    var mandatoryTot = 0;
    for(var difficultyID = 1; difficultyID <= maxDifficulty; difficultyID++){
        randomTot += difficultiesRandom[difficultyID];
        mandatoryTot += difficultiesMandatory[difficultyID];

        if(difficultiesMandatory[difficultyID] > 0){
            $("#qDifficulty"+difficultyID+"_tot .questionsRandomTot").text(difficultiesRandom[difficultyID]);
            $("#qDifficulty"+difficultyID+"_tot .questionsMandatoryTot").text("+"+difficultiesMandatory[difficultyID]+"=");
        }
        $("#qDifficulty"+difficultyID+"_tot .questionsTot").text(difficultiesRandom[difficultyID]+difficultiesMandatory[difficultyID]);
    }

    if(mandatoryTot > 0){
        $("#questionsTot .questionsRandomTot").text(randomTot);
        $("#questionsTot .questionsMandatoryTot").text("+"+mandatoryTot+"=");
    }
    $("#questionsTot .questionsTot").text(randomTot+mandatoryTot);

}