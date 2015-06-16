/**
 * File: Showsettingsinfo.js
 * User: Masterplan
 * Date: 27/05/14
 * Time: 13:24
 * Desc: Shows test setting's info and allows to edit its informations
 */

/*
 * questionsDistribution[idTopic][idDifficulty] = [#random , #mandatory]
 * Defined and initialized in php view
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
                                                            { className: "dEasy"},
                                                            { className: "dMedium"},
                                                            { className: "dHard"},
                                                            { className: "dTot"}
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
        }else{
            $("#qTopic"+topicID+"_tot .questionsRandomTot").text("");
            $("#qTopic"+topicID+"_tot .questionsMandatoryTot").text("");
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
        }else{
            $("#qDifficulty"+difficultyID+"_tot .questionsRandomTot").text("");
            $("#qDifficulty"+difficultyID+"_tot .questionsMandatoryTot").text("");
        }
        $("#qDifficulty"+difficultyID+"_tot .questionsTot").text(difficultiesRandom[difficultyID]+difficultiesMandatory[difficultyID]);
    }

    if(mandatoryTot > 0){
        $("#questionsTot .questionsRandomTot").text(randomTot);
        $("#questionsTot .questionsMandatoryTot").text("+"+mandatoryTot+"=");
    }else{
        $("#questionsTot .questionsRandomTot").text("");
        $("#questionsTot .questionsMandatoryTot").text("");
    }
    $("#questionsTot .questionsTot").text(randomTot+mandatoryTot);

}

/**
 *  @name   changeTopicQuestions
 *  @descr  Calculates new questions summaries for topics and difficulties sections and updates summary boxes and question field
 *  @param  topicID         Integer       Question's topic ID
 *  @param  difficultyID    Integer       Question's difficulty ID
 */
function changeTopicQuestions(topicID, difficultyID){

    var newValue = $("#r-"+topicID+"-"+difficultyID).val();
    if(newValue == "" || isNaN(newValue)){
        newValue = 0;
    }
    if(newValue < 0){
        newValue = 0;
        $("#r-"+topicID+"-"+difficultyID).val(0)
    }

    questionsDistribution[topicID][difficultyID][0] = newValue;
    updateQuestionsSummaries();
}