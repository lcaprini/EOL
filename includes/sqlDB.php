<?php
/**
 * File: sqlDB.php
 * User: Masterplan
 * Date: 3/15/13
 * Time: 12:09 PM
 * Desc: Interface class for MySql database
 */

class sqlDB {

    private $dbHost;
    private $dbPort;
    private $dbName;
    private $dbUsername;
    private $dbPassword;
    private $mysqli;
    private $active = false;
    private $error;
    public 	$result;
	private $result2;

    /**
     * @name    sqlDB
     * @descr   Creates a sqlDB object
     */
    public function sqlDB(){

        global $config;

        $this->dbHost = $config['dbHost'];
        $this->dbPort = $config['dbPort'];
        $this->dbName = $config['dbName'];
        $this->dbUsername = $config['dbUsername'];
        $this->dbPassword = $config['dbPassword'];
    }

/*******************************************************************
*                              Login                               *
*******************************************************************/

    /**
     * @name    qLogin
     * @param   $email      String  Login email
     * @param   $password   String  Login password
     * @return  array|null  User's informations
     * @descr   Define and execute queries for login
     */
    public function qLogin($email, $password){
        $mysqli = $this->connect();

        $query = "SELECT idUser, name, surname, email, password, role, alias
                  FROM Users
                  JOIN Languages
                      ON fkLanguage = idLanguage
                  WHERE
	                  email = ?
                      AND
                      password = ?";

        // Prepare statement
        if (!($stmt = $mysqli->prepare($query))) {
            echo 'Prepare failed: (' . $mysqli->errno . ') ' . $mysqli->error;
        }
        // Binding parameters
        if (!$stmt->bind_param('ss', $email, $password)) {
            echo 'Binding parameters failed: (' . $stmt->errno . ') ' . $stmt->error;
        }
        // Execute query
        if (!$stmt->execute()) {
            echo 'Execute failed: (' . $stmt->errno . ') ' . $stmt->error;
        }
        $stmt->bind_result($i, $n, $s, $e, $p, $r, $l);
        if($stmt->fetch()){
            $result = array(
                'id'        => $i,
                'name'      => $n,
                'surname'   => $s,
                'email'     => $e,
                'lang'      => $l,
                'role'      => $r);
        }else{
            $result = null;
        }

        $mysqli->close();
        return $result;
    }

/*******************************************************************
*                            Subjects                              *
*******************************************************************/

    /**
     * @name    qSubject
     * @param   $idUser       String        Logged user's ID
     * @param   $role         String        Logged user's role
     * @return  Boolean
     * @descr   Get a list of subjects
     */
    public function qSubjects($idUser, $role){
        global $log;

        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            if(($role == 't') || ($role == 'at')){
                $query = "SELECT *
                          FROM
                              Subjects
                          WHERE
                              idSubject IN (
                                  SELECT fkSubject
                                  FROM
                                      Users_Subjects
                                  WHERE
                                      fkUser = '$idUser'
                              )
                          ORDER BY name";
                $this->execQuery($query);
            }else{
                $query = "SELECT *
                          FROM
                              Subjects
                          ORDER BY name";
                $this->execQuery($query);
            }
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qUpdateSubjectInfo
     * @param   $idSubject       String        Requested Subject's ID
     * @param   $name            String        Subject's name
     * @param   $desc            String        Subject's description
     * @param   $teachers        Array         Assigned teachers
     * @return  Boolean
     * @descr   Returns true if info was saved successfully, false otherwise
     */
    public function qUpdateSubjectInfo($idSubject, $name, $desc, $teachers){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $data = $this->prepareData(array($name, $desc));
            $queries = array();
            $query = "UPDATE Subjects
                      SET
                          name = '$data[0]',
                          description = '$data[1]'
                      WHERE
                          idSubject = '$idSubject'";
            array_push($queries, $query);
            $query = "DELETE
                      FROM Users_Subjects
                      WHERE
                          fkSubject = '$idSubject'";
            array_push($queries, $query);
            if(count($teachers) > 0){
                $query = "INSERT INTO Users_Subjects (fkUser, fkSubject)
                          VALUES ";
                foreach($teachers as $teacher){
                    $query .= "('$teacher', '$idSubject'),\n";
                }
                $query = substr_replace($query , '', -2);
                array_push($queries, $query);
            }
            $this->execTransaction($queries);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qNewSubject
     * @param   $name          String        Subject's name
     * @param   $desc          String        Subject's description
     * @param   $lang          String        Subject's main language
     * @return  Boolean
     * @descr   Returns true if info was saved successfully, false otherwise
     */
    public function qNewSubject($name, $desc, $lang){
        global $log, $user;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $queries = array();
            $data = $this->prepareData(array($name, $desc, $lang));
            $query = "INSERT INTO Subjects (name, description, fkLanguage)
                      VALUES ('$data[0]', '$data[1]', '$data[2]')";
            array_push($queries, $query);
            $query = "SET @subID = LAST_INSERT_ID()";
            array_push($queries, $query);
            $query = "INSERT INTO Users_Subjects (fkUser , fkSubject)
                      VALUES ('$user->id', @subID)";
            array_push($queries, $query);
            $query = "SELECT @subID";
            array_push($queries, $query);
            $this->execTransaction($queries);

        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qDeleteSubject
     * @param   $idSubject         String        Subject's ID
     * @return  Boolean
     * @descr   Returns true if subject and all its related was successfully deleted, false otherwise
     */
    public function qDeleteSubject($idSubject){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $query = "DELETE FROM Subjects
                      WHERE idSubject = '$idSubject'";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

/*******************************************************************
*                              Topics                              *
*******************************************************************/

    /**
     * @name    qUpdateTopicInfo
     * @param   $idTopic        String        Requested Topic's ID
     * @param   $name           String        Topic's name
     * @param   $desc           String        Topic's description
     * @return  Boolean
     * @descr   Returns true if info was saved successfully, false otherwise
     */
    public function qUpdateTopicInfo($idTopic, $name, $desc){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $data = $this->prepareData(array($idTopic, $name, $desc));
            $query = "UPDATE Topics
                      SET
                          name = '$data[1]',
                          description = '$data[2]'
                      WHERE
                          idTopic = '$data[0]'";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qGetEditAndDeleteConstraints
     * @param   $action         String          Constraint's action
     * @param   $table          String          Constraint's table
     * @param   $params         Array           Constraint's array values
     * @return  Boolean
     * @descr   Gets the list of costraints
     */
    public function qGetEditAndDeleteConstraints($action, $table, $params){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $query = "";
            switch($action){
                case "delete" :
                    switch($table){
                        case "topic" :
                            $query = "SELECT *
                                      FROM
                                          Questions_TestSettings
                                          JOIN TestSettings ON fkTestSetting = idTestSetting
                                      WHERE
                                          fkQuestion IN (SELECT idQuestion
                                                         FROM Questions
                                                         WHERE
                                                             fkTopic = '".$params[0]."')
                                      GROUP BY idTestSetting"; break;
                        case "question1" :          // Check if question is in test settings
                            $query = "SELECT *
                                      FROM
                                          Questions_TestSettings
                                      JOIN
                                          TestSettings ON idTestSetting = fkTestSetting
                                      WHERE
                                          fkQuestion = '$params[0]'
                                      GROUP BY idTestSetting"; break;
                        case "question2" :          // Check if question is in History or Sets_Questions
                            $query = "SELECT fkQuestion
                                      FROM
                                          History
                                      WHERE
                                          fkQuestion = '$params[0]'
                                      UNION
                                      SELECT fkQuestion
                                      FROM
                                          Sets_Questions
                                      WHERE
                                          fkQuestion = '$params[0]'"; break;
                        case "answer1" :          // Check if question is in test settings
                            $query = "SELECT *
                                      FROM
                                          Questions_TestSettings
                                      JOIN
                                          TestSettings ON idTestSetting = fkTestSetting
                                      WHERE
                                          fkQuestion = '$params[0]'
                                      GROUP BY idTestSetting"; break;
                        case "answer2" :          // Check if question is in History or Sets_Questions
                            $query = "SELECT fkQuestion
                                      FROM
                                          History
                                      WHERE
                                          fkQuestion = '$params[0]'
                                      UNION
                                      SELECT fkQuestion
                                      FROM
                                          Sets_Questions, Sets
                                      WHERE
                                          fkQuestion = '$params[0]'
                                          AND
                                          assigned = 'y'"; break;
                    }; break;
                case "edit" :
                    switch($table){
                        case "testsetting" :
                            $query = "SELECT *
                                      FROM Exams
                                      WHERE
                                          status != 'a'
                                          AND
                                          fkTestSetting = '".$params[0]."'"; break;
                        case "question1" :
                            $query = "SELECT *
                                      FROM
                                          Questions_TestSettings
                                      JOIN
                                          TestSettings ON idTestSetting = fkTestSetting
                                      WHERE
                                          fkQuestion = '$params[0]';"; break;
                        case "question2" :
                            $query = "SELECT fkQuestion
                                      FROM
                                          History
                                      WHERE
                                          fkQuestion = '$params[0]'
                                      UNION
                                      SELECT fkQuestion
                                      FROM
                                          Sets_Questions, Sets
                                      WHERE
                                          fkQuestion = '$params[0]'
                                          AND
                                          assigned = 'y'"; break;
                        case "answer1" :
                            $query = "SELECT *
                                      FROM
                                          Questions_TestSettings
                                      JOIN
                                          TestSettings ON idTestSetting = fkTestSetting
                                      WHERE
                                          fkQuestion = '$params[0]';"; break;
                        case "answer2" :
                            $query = "SELECT fkQuestion
                                      FROM
                                          History
                                      WHERE
                                          fkQuestion = '$params[0]'
                                      UNION
                                      SELECT fkQuestion
                                      FROM
                                          Sets_Questions, Sets
                                      WHERE
                                          fkQuestion = '$params[0]'
                                          AND
                                          assigned = 'y'"; break;
                    } break;
                case 'create' :
                    switch($table){
                        case "answer1" :
                            $query = "SELECT *
                                      FROM
                                          Questions_TestSettings
                                      JOIN
                                          TestSettings ON idTestSetting = fkTestSetting
                                      WHERE
                                          fkQuestion = '$params[0]';"; break;
                        case "answer2" :
                            $query = "SELECT fkQuestion
                                      FROM
                                          History
                                      WHERE
                                          fkQuestion = '$params[0]'
                                      UNION
                                      SELECT fkQuestion
                                      FROM
                                          Sets_Questions, Sets
                                      WHERE
                                          fkQuestion = '$params[0]'
                                          AND
                                          assigned = 'y'"; break;
                    } break;
            }
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qDeleteTopic
     * @param   $idTopic         String        Topic's ID
     * @return  Boolean
     * @descr   Returns true if topic and all its related was successfully deleted, false otherwise
     */
    public function qDeleteTopic($idTopic){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        $queries = array();
        try{

            $query = "CREATE VIEW questionstoflag AS
                          (SELECT idQuestion
                          FROM History
                              JOIN Questions ON idQuestion = fkQuestion
                          WHERE
                              fkTopic = '$idTopic'
                              AND
                              idQuestion != 'd'
                          GROUP BY idQuestion)";
            array_push($queries, $query);
            $query = "UPDATE Questions
                      SET
                          status = 'd'
                      WHERE
                          idQuestion IN (SELECT idQuestion
                                         FROM
                                             questionstoflag)";
            array_push($queries, $query);
            $query = "DELETE FROM Questions
                      WHERE
                          fkTopic = '$idTopic'
                          AND
                          idQuestion NOT IN (SELECT idQuestion
                                             FROM
                                                 questionstoflag)";
            array_push($queries, $query);
            $query = "DROP VIEW questionstoflag";
            array_push($queries, $query);
            $query = "DELETE FROM Topics
                      WHERE
                          idTopic = '$idTopic'";
            array_push($queries, $query);

            $this->execTransaction($queries);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qNewTopic
     * @param   $idSubject      String        Subject's ID
     * @param   $name           String        Topic's name
     * @param   $desc           String        Topic's description
     * @return  Boolean
     * @descr   Returns true if topic was saved created, false otherwise
     */
    public function qNewTopic($idSubject, $name, $desc){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        $queries = array();
        try{
            $data = $this->prepareData(array($name, $desc));
            $query = "INSERT INTO Topics (name, description, fkSubject)
                  VALUES ('$data[0]', '$data[1]', '$idSubject')";
            array_push($queries, $query);
            $query = "SELECT LAST_INSERT_ID()";
            array_push($queries, $query);
            $this->execTransaction($queries);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

/*******************************************************************
*                            Questions                             *
*******************************************************************/

    /**
     * @name    qQuestions
     * @param   $idSubject       String        Subject's ID
     * @param   $idTopic         String        Topic's ID
     * @param   $idQuestion      String        Question's ID
     * @param   $idLanguage      String        Question's language
     * @return  Boolean
     * @descr   Get questions info by subject ID, topic ID or question ID
     */
    public function qQuestions($idSubject, $idTopic, $idQuestion = null, $idLanguage = null){
        global $log;
        $ack = true;
        $this->result = null;

        try{
            if($idQuestion == null){
                $this->mysqli = $this->connect();
                $topics = array();
                if($idTopic == '-1'){                         // No topic selected => Show all subject's questions
                    $query = "SELECT idTopic
                              FROM
                                  Topics
                              WHERE
                                  fkSubject = '$idSubject'";
                    $this->execQuery($query);
                    while($row = $this->nextRowAssoc()){
                        array_push($topics, $row['idTopic']);
                    }
                }else{
                    array_push($topics, $idTopic);
                }
                if(count($topics) > 0){
                    $query = "SELECT idQuestion, status, translation, type, difficulty, fkLanguage, idTopic, name, shortText
                              FROM Questions
	                              JOIN TranslationQuestions ON idQuestion = fkQuestion
	                              JOIN Topics ON idTopic = fkTopic
                              WHERE
                                  status != 'd'
                                  AND
                                  fkTopic IN (".implode(',', $topics).")";
                    if($idLanguage != null)
                        $query .= " AND fkLanguage = '$idLanguage'";
                    $query .= " ORDER BY idQuestion";
                }
            }else{                                             // Returns info about only one question
                $query = "SELECT *
                          FROM Questions
                          WHERE
                              idQuestion = '$idQuestion'";
                if($idLanguage != null)
                    $query .= " AND fkLanguage = '$idLanguage'";
            }
            $this->mysqli = $this->connect();
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qQuestionInfo
     * @param   $idQuestion         String        Question's ID
     * @param   $idLanguage         String        Question's language ID
     * @return  Boolean
     * @descr   Get infos about selected question
     */
    public function qQuestionInfo($idQuestion, $idLanguage = null){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $query = "SELECT *
                      FROM Questions
                      	  JOIN Topics ON idTopic = fkTopic
                      	  JOIN TranslationQuestions ON idQuestion = fkQuestion
                      	  JOIN Languages ON idLanguage = fkLanguage
                      WHERE
                          idQuestion = '$idQuestion'";
            if($idLanguage != null)
                $query .= " AND fkLanguage = '$idLanguage'";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qNewQuestion
     * @param   $idTopic            String        Topic's ID
     * @param   $type               String        Question's type
     * @param   $difficulty         String        Question's difficulty
     * @param   $extras             String        Question's extras
     * @param   $shortText          String        Question's difficulty
     * @param   $translationsQ      Array         Question's translations
     * @return  Boolean
     * @descr   Update all questions details (infos and translations)
     */
    public function qNewQuestion($idTopic, $type, $difficulty, $extras, $shortText, $translationsQ){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        $queries = array();
        try{
            $data = $this->prepareData(array($type, $difficulty, $extras, $shortText));
            $query = "INSERT INTO Questions (type, difficulty, extra, shortText, fkTopic)
                      VALUES ('$data[0]', '$data[1]', '$data[2]', '$data[3]','$idTopic')";
            array_push($queries, $query);
            $query = "UPDATE Questions
                      SET
                          fkRootQuestion = LAST_INSERT_ID()
                      WHERE
                          idQuestion = LAST_INSERT_ID()";
            array_push($queries, $query);
            $query = "INSERT INTO TranslationQuestions
                      VALUES ";
            foreach($translationsQ as $idLanguage => $translation){
                if($translation != null){
                    $data = $this->prepareData(array($translation));
                    $query .= "(LAST_INSERT_ID(), '$idLanguage', '$data[0]'),\n";
                }
            }
            $query = substr_replace($query , '', -2);       // Remove last coma
            array_push($queries, $query);
            $query = "SELECT LAST_INSERT_ID()";
            array_push($queries, $query);
            $this->execTransaction($queries);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qChangeQuestionStatus
     * @param   $idQuestion         String        Question's ID
     * @param   $status             String        Question's status
     * @return  Boolean
     * @descr   Change question's status
     */
    public function qChangeQuestionStatus($idQuestion, $status){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $query = "UPDATE Questions
                      SET
                          status = '$status'
                      WHERE
                          idQuestion = '$idQuestion'";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qDuplicateQuestion
     * @param   $idQuestion             String        Question's ID
     * @param   $updateMandatory        Boolean       If true update question's ID on Questions_TestSettings table
     * @param   $idAnswerToEdit         String        ID of answer to delete or edit
     * @return  Boolean
     * @descr   Duplicates question and its answers with all translations
     */
    public function qDuplicateQuestion($idQuestion, $updateMandatory, $idAnswerToEdit=null){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        $queries = array();
        try{
            $query = "INSERT INTO Questions (type, difficulty, status, extra, shortText, fkRootQuestion, fkTopic)
                      SELECT type, difficulty, status, extra, shortText, fkRootQuestion, fkTopic
                      FROM
                          Questions
                      WHERE
                          idQuestion = '$idQuestion'";
            array_push($queries, $query);
            $query = "SET @questID = LAST_INSERT_ID()";
            array_push($queries, $query);
            $query = "UPDATE Questions
                      SET
                          status = 'd'
                      WHERE
                          idQuestion = '$idQuestion'";
            array_push($queries, $query);
            $query = "INSERT INTO TranslationQuestions
                      SELECT @questID, fkLanguage, translation
                      FROM TranslationQuestions
                      WHERE
                          fkQuestion = '$idQuestion'";
            array_push($queries, $query);
            $query = "SELECT *
                      FROM
                          Answers
                      WHERE
                          fkQuestion = '$idQuestion'";
            $this->execQuery($query);
            while($answers = $this->nextRowAssoc()){
                $query = "INSERT INTO Answers (score, fkQuestion)
                          VALUES ('".$answers['score']."', @questID)";
                array_push($queries, $query);
                $query = "SET @aswrID = LAST_INSERT_ID()";
                array_push($queries, $query);
                $query = "INSERT INTO TranslationAnswers
                          SELECT @aswrID, fkLanguage, translation
                          FROM TranslationAnswers
                          WHERE
                              fkAnswer = '".$answers['idAnswer']."'";
                array_push($queries, $query);
                if($idAnswerToEdit == $answers['idAnswer']){
                    $query = "SET @newAswrID = @aswrID";
                    array_push($queries, $query);
                }
            }
            if($updateMandatory){
                $query = "UPDATE Questions_TestSettings
                          SET
                              fkQuestion = @questID
                          WHERE
                              fkQuestion = '$idQuestion'";
                array_push($queries, $query);
            }
            if($idAnswerToEdit != null){
                $query = "SELECT @questID, @newAswrID";
            }else{
                $query = "SELECT @questID";
            }
            array_push($queries, $query);

            $this->mysqli = $this->connect();
            $this->execTransaction($queries);

        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;

    }

    /**
     * @name    qUpdateQuestionInfo
     * @param   $idQuestion         String        Question's ID
     * @param   $idTopic            String        Topic's ID
     * @param   $difficulty         String        Question's difficulty
     * @param   $extras             String        Question's extras
     * @param   $shortText          String        Question's short text
     * @param   $translationsQ      Array         Question's translations
     * @return  Boolean
     * @descr   Update all questions details (infos and translations)
     */
    public function qUpdateQuestionInfo($idQuestion, $idTopic, $difficulty, $extras, $shortText, $translationsQ){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        $queries = array();
        try{
            $data = $this->prepareData(array($difficulty, $extras, $shortText));
            $query = "UPDATE Questions
                      SET
                          difficulty = '$data[0]',
                          extra = '$data[1]',
                          shortText = '$data[2]',
                          fkTopic = '$idTopic'
                      WHERE
                          idQuestion = '$idQuestion'";
            array_push($queries, $query);
            $query = "DELETE FROM TranslationQuestions
                          WHERE
                              fkQuestion = '$idQuestion'";
            array_push($queries, $query);
            foreach($translationsQ as $idLanguage => $translation){
                if($translation != null){
                    $data = $this->prepareData(array($translation));
                    $query = "INSERT INTO TranslationQuestions
                              VALUES ('$idQuestion', '$idLanguage', '$data[0]')";
                    array_push($queries, $query);
                }
            }
            $this->execTransaction($queries);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qDeleteQuestion
     * @param   $idQuestion         String        Question's ID
     * @param   $remove             Boolean       If true delete question from database, else only flag status to 'd'
     * @return  Boolean
     * @descr   Return true if question is successfully deleted, false otherwise
     */
    public function qDeleteQuestion($idQuestion, $remove=true){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            if($remove){
                $query = "DELETE FROM Questions
                          WHERE idQuestion = '$idQuestion'";
            }else{
                $query = "UPDATE Questions
                          SET
                              status = 'd'
                          WHERE
                              idQuestion = '$idQuestion'";
            }
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

/*******************************************************************
*                             Answers                              *
*******************************************************************/

    /**
     * @name    qAnswerInfo
     * @param   $idAnswer         String        Answer's ID
     * @return  Boolean
     * @descr   Get infos about selected answer
     */
    public function qAnswerInfo($idAnswer){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $query = "SELECT *
                      FROM Answers, TranslationAnswers, Languages
                      WHERE
                          idAnswer = '$idAnswer'
                          AND
                          idAnswer = fkAnswer
                          AND
                          idLanguage = fkLanguage";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qNewAnswer
     * @param   $idQuestion         String        Question's ID
     * @param   $score              String        Answer's type
     * @param   $translationsA      Array         Answer's translations
     * @return  Boolean
     * @descr   Update all answers details (infos and translations)
     */
    public function qNewAnswer($idQuestion, $score, $translationsA){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        $queries = array();
        try{
            $query = "INSERT INTO Answers (score, fkQuestion)
                      VALUES ('$score', '$idQuestion')";
            array_push($queries, $query);
            if(count($translationsA) > 0){
                $index = 0;
                $query = "INSERT INTO TranslationAnswers
                      VALUES ";
                while($index < count($translationsA)){
                    if($translationsA[$index] != null){
                        $data2 = $this->prepareData(array($translationsA[$index]));
                        $query .= "(LAST_INSERT_ID(), '$index', '$data2[0]'),\n";
                    }
                    $index++;
                }
                $query = substr_replace($query , '', -2);       // Remove last coma
                array_push($queries, $query);
            }
            $query = "SELECT LAST_INSERT_ID()";
            array_push($queries, $query);
            $this->execTransaction($queries);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qUpdateAnswerInfo
     * @param   $idAnswer           String        Answer's ID
     * @param   $score              String        Answer's score
     * @param   $translationsA      Array         Answer's translations
     * @return  Boolean
     * @descr   Update all answer details (infos and translations)
     */
    public function qUpdateAnswerInfo($idAnswer, $score, $translationsA){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        $queries = array();
        try{
            $data = $this->prepareData(array($idAnswer, $score));
            $query = "UPDATE Answers
                      SET
                          score = '$data[1]'
                      WHERE
                          idAnswer = '$data[0]'";
            array_push($queries, $query);
            $query = "DELETE FROM TranslationAnswers
                      WHERE
                          fkAnswer = '$data[0]'";
            array_push($queries, $query);
            $index = 0;
            while($index < count($translationsA)){
                if($translationsA[$index] != null){
                    $data2 = $this->prepareData(array($translationsA[$index]));
                    $query = "INSERT INTO TranslationAnswers
                              VALUES ('$data[0]', '$index', '$data2[0]')";
                    array_push($queries, $query);
                }
                $index++;
            }
            $this->execTransaction($queries);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qDeleteAnswer
     * @param   $idAnswer         String        Question's ID
     * @return  Boolean
     * @descr   Return true if answer is successfully deleted, false otherwise
     */
    public function qDeleteAnswer($idAnswer){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $query = "DELETE FROM Answers
                      WHERE idAnswer = '$idAnswer'";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

/*******************************************************************
*                              Exams                               *
*******************************************************************/

    /**
     * @name    qExams
     * @return  Boolean
     * @descr   Get exams's list of teacher
     */
    public function qExams(){
        global $log, $user;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $query = "SELECT idExam, Exams.name exam, status, Subjects.name subject,
                             TestSettings.name settings, password, datetime, idSubject, idTestSetting, scale
                      FROM
                          Exams
                              LEFT JOIN Subjects ON Exams.fkSubject = Subjects.idSubject
                              LEFT JOIN TestSettings ON Exams.fkTestSetting = TestSettings.idTestSetting
                              LEFT JOIN Users_Subjects ON Subjects.idSubject = Users_Subjects.fkSubject
                      WHERE fkUser = '$user->id'
                      ORDER BY datetime DESC";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qExamsAvailable
     * @param   $idSubject              String          Subject's ID
     * @param   $idUser                 String          Student's ID
     * @return  Boolean
     * @descr   Get list of exams for requested subject
     */
    public function qExamsAvailable($idSubject, $idUser){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $query = "SELECT *
                      FROM Exams AS E
                      WHERE
                          E.fkSubject = '$idSubject'
                          AND (
                              ((E.status = 'w' OR E.status = 's') AND NOW() BETWEEN E.regStart AND E.regEnd)
                              OR
                              ((E.status = 'w' OR E.status = 's') AND EXISTS (
                                                                          SELECT *
                                                                          FROM Tests AS T
                                                                          WHERE
                                                                              T.fkExam = E.idExam
                                                                              AND
                                                                              T.fkUser = '$idUser')
                              )
                          )";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qExamsInProgress
     * @param   $idTeacher              String|null          Teachers's ID
     * @return  Boolean
     * @descr   Get list of available exams for requested teacher
     */
    public function qExamsInProgress($idTeacher=null){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $query = "SELECT idExam, E.name AS examName, S.name AS subjectName, fkSubject, datetime, status
                      FROM Exams AS E
                      JOIN Subjects AS S ON S.idSubject = E.fkSubject
                      WHERE
                          E.status != 'a' ";
            if($idTeacher != null)
                $query .= "AND
                           E.fkSubject IN (SELECT fkSubject
                                           FROM Users_Subjects
                                           WHERE
                                           fkUser = '$idTeacher')";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qNewExam
     * @param   $name               String        Exam's name
     * @param   $idSubject          String        Exam's subject ID
     * @param   $idTestSetting      String        Test Settings's ID
     * @param   $datetime           String        Exam's day and time
     * @param   $desc               String        Exam's description
     * @param   $regStart           String        Exam's registration start day and time
     * @param   $regEnd             String        Exam's registration end day and time
     * @param   $rooms              String        Exam's rooms list
     * @param   $password           String        Exam's password
     * @return  Boolean
     * @descr   Returns true if info was saved successfully, false otherwise
     */
    public function qNewExam($name, $idSubject, $idTestSetting, $datetime, $desc, $regStart, $regEnd, $rooms, $password){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        $queries = array();
        try{
            $data = $this->prepareData(array($name, $desc));
            $query = "INSERT INTO Exams (name, datetime, description, regStart, regEnd, password, fkTestSetting, fkSubject)
                      VALUES ('$data[0]', '$datetime', '$data[1]', $regStart, $regEnd, '$password', '$idTestSetting', '$idSubject')";
            array_push($queries, $query);
            $query = "SET @examID = LAST_INSERT_ID()";
            array_push($queries, $query);
            $rooms = json_decode(stripslashes($rooms), true);
            if(count($rooms) > 0){
                $query = "INSERT INTO Exams_Rooms (fkExam, fkRoom)
                          VALUES ";
                for($index = 0; $index < count($rooms); $index++){
                    $query .= "(@examID, '$rooms[$index]'),\n";
                }
                $query = substr_replace($query , '', -2);
                array_push($queries, $query);
            }
            $query = "SELECT idExam, Exams.name exam, status, Subjects.name subject, TestSettings.name settings, password, datetime, idSubject, idTestSetting
                      FROM
                          Exams
                              LEFT JOIN Subjects ON Exams.fkSubject = Subjects.idSubject
                              LEFT JOIN TestSettings ON Exams.fkTestSetting = TestSettings.idTestSetting
                      WHERE idExam = @examID";
            array_push($queries, $query);
            $this->execTransaction($queries);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qUpdateExamInfo
     * @param   $idExam             String        Requested Exam's ID
     * @param   $name               String        Exam's name
     * @param   $datetime           String        Exam's day and time
     * @param   $desc               String        Exam's description
     * @param   $regStart           String        Exam's registration start day and time
     * @param   $regEnd             String        Exam's registration end day and time
     * @param   $rooms              String        Exam's rooms list
     * @param   $password           String        Exam's new password
     * @return  Boolean
     * @descr   Returns true if info was saved successfully, false otherwise
     */
    public function qUpdateExamInfo($idExam, $name, $datetime, $desc, $regStart, $regEnd, $rooms, $password=null){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        $queries = array();
        try{
            if($password != null){
                $query = "UPDATE Exams
                          SET
                              password = '$password'
                          WHERE
                               idExam = '$idExam'";
                $this->execQuery($query);
            }else{
                $data = $this->prepareData(array($name, $desc));
                if(($this->qSelect('Exams', 'idExam', $idExam)) && ($examInfo = $this->nextRowAssoc())){
                    if($examInfo['status'] == 'a'){
                        die(ttEExamArchived);
                    }else{
                        $query = "UPDATE Exams
                                  SET
                                      name = '$data[0]',
                                      datetime = '$datetime',
                                      description = '$data[1]',
                                      regStart = $regStart,
                                      regEnd = $regEnd
                                  WHERE
                                       idExam = '$idExam'";
                        array_push($queries, $query);
                        $query = "DELETE
                                  FROM Exams_Rooms
                                  WHERE
                                      fkExam = '$idExam'";
                        array_push($queries, $query);
                        $rooms = json_decode(stripslashes($rooms), true);
                        if(count($rooms) > 0){
                            $query = "INSERT INTO Exams_Rooms (fkExam, fkRoom)
                                      VALUES ";
                            for($index = 0; $index < count($rooms); $index++){
                                $query .= "('$idExam', '$rooms[$index]'),\n";
                            }
                            $query = substr_replace($query , '', -2);
                            array_push($queries, $query);
                        }
                        $query = "SELECT idExam, Exams.name exam, status, Subjects.name subject, TestSettings.name settings, password, datetime, idSubject, idTestSetting
                                  FROM
                                      Exams
                                          LEFT JOIN Subjects ON Exams.fkSubject = Subjects.idSubject
                                          LEFT JOIN TestSettings ON Exams.fkTestSetting = TestSettings.idTestSetting
                                  WHERE idExam = '$idExam'";
                        array_push($queries, $query);
                        $this->execTransaction($queries);
                    }
                }
            }
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }
        return $ack;
    }

    /**
     * @name    qChangeExamStatus
     * @param   $idExam     String      Exam's ID
     * @param   $status     String      Exam's new status
     * @return  Integer
     * @descr   Return true if exam was successfully started, false otherwise
     */
    public function qChangeExamStatus($idExam, $status){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $query = "UPDATE Exams
                      SET status = '$status'
                      WHERE idExam = '$idExam'";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qArchiveExam
     * @param   $idExam     String      Exam's ID
     * @return  Boolean
     * @descr   Return true if exam was successfully archived, false otherwise
     */
    public function qArchiveExam($idExam){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $queries = array();
            $query = "DELETE
                      FROM Sets
                      WHERE
                         fkExam = '$idExam'";
            array_push($queries, $query);
            $query = "UPDATE Exams
                      SET status = 'a'
                      WHERE idExam = '$idExam'";
            array_push($queries, $query);

            $this->mysqli = $this->connect();
            $this->execTransaction($queries);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qDeleteExam
     * @param   $idExam     String      Exam's ID
     * @return  Boolean
     * @descr   Return true if exam was successfully deleted, false otherwise
     */
    public function qDeleteExam($idExam){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $queries = array();
            // Delete exam (innoDB engine and its foreign key do the rest 8-) )
            $query = "DELETE FROM Exams
                      WHERE idExam = '$idExam'";
            array_push($queries, $query);
            $this->execTransaction($queries);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qExamRegistrationsList
     * @param   $idExam       String        Requested Exam's ID
     * @return  Boolean
     * @descr   Get list of all users registered to requested exam
     */
    public function qExamRegistrationsList($idExam){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $query = "SELECT idTest, timeStart, timeEnd, scoreTest, scoreFinal, status, fkUser, name, surname, email
                      FROM Tests
                      JOIN Users
                          ON Tests.fkUser = Users.idUser
                      WHERE
                          fkExam = '$idExam'
                      ORDER BY surname, name";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qStudentsNotRegistered
     * @param   $idExam       String        Requested Exam's ID
     * @return  Boolean
     * @descr   Get list of all users not registered to requested exam
     */
    public function qStudentsNotRegistered($idExam){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $query = "SELECT *
                      FROM Users
                      WHERE
                          role LIKE '%s%'
                          AND
                          idUser NOT IN (SELECT fkUser
                                         FROM Tests
                                         WHERE
                                            fkExam = '$idExam')";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qCheckRegistration
     * @param   $idExam     String        Requested Exam's ID
     * @param   $idUser     String        Requested Student's ID
     * @return  Boolean
     * @descr   Get the Tests's row with specific exam and student, if exist
     */
    public function qCheckRegistration($idExam, $idUser){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $query = "SELECT *
                      FROM Tests
                      WHERE
                          fkExam = '$idExam'
                          AND
                          fkUser = '$idUser'";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

/*******************************************************************
*                              Rooms                               *
*******************************************************************/

    /**
     * @name    qRoomsExam
     * @param   $idExam         String      Exam's ID
     * @return  Boolean
     * @descr   Get list of all rooms added for an exam
     */
    public function qRoomsExam($idExam){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $query = "SELECT *
                      FROM
                          Exams_Rooms
                          JOIN Rooms ON idRoom = fkRoom
                      WHERE
                          fkExam = '$idExam'
                      ORDER BY fkRoom";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qNewRoom
     * @param   $name           String        Room's name
     * @param   $desc           String        Room's description
     * @param   $ipStart        String        Room's IP start
     * @param   $ipEnd          String        Room's IP End
     * @return  Boolean
     * @descr   Returns true if room was successfully created, false otherwise
     */
    public function qNewRoom($name, $desc, $ipStart, $ipEnd){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $data = $this->prepareData(array($name, $desc));
            $query = "INSERT INTO Rooms (name, description, ipStart, ipEnd)
                      VALUES ('$data[0]', '$data[1]', '$ipStart', '$ipEnd')";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qUpdateRoomInfo
     * @param   $idRoom     String        Requested Room's ID
     * @param   $name       String        Room's name
     * @param   $desc       String        Room's description
     * @param   $ipStart    String        Room's IP start
     * @param   $ipEnd      String        Room's IP end
     * @return  Boolean
     * @descr   Returns true if room's infos was successfully saved, false otherwise
     */
    public function qUpdateRoomInfo($idRoom, $name, $desc, $ipStart, $ipEnd){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $data = $this->prepareData(array($name, $desc));
            $query = "UPDATE Rooms
                      SET
                          name = '$data[0]',
                          description = '$data[1]',
                          ipStart = '$ipStart',
                          ipEnd = '$ipEnd'
                      WHERE
                          idRoom = '$idRoom'";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qDeleteRoom
     * @param   $idRoom         String      Room's ID
     * @return  Boolean
     * @descr   Return true if requested room was successfully deleted, else otherwise
     */
    public function qDeleteRoom($idRoom){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $query = "DELETE
                      FROM
                          Rooms
                      WHERE
                          idRoom = '$idRoom'
                          AND
                          idRoom NOT IN (
                                      SELECT fkRoom
                                          FROM Exams_Rooms
                                              JOIN Exams ON idExam = fkExam
                                          WHERE
                                              fkRoom = '$idRoom'
                                              AND
                                              status != 'a'
                                      )";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

/*******************************************************************
*                           Test Setting                           *
*******************************************************************/

    /**
     * @name    qShowTopicsForSetting
     * @param   $idTestSetting      String        Requested Test Settings ID
     * @return  Boolean
     * @descr   Returns true if info was saved successfully, false otherwise
     */
    public function qShowTopicsForSetting($idTestSetting){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $data = $this->prepareData(array($idTestSetting));
            $query= "SELECT idTopic, Topics.name AS topicName, numQuestions
                     FROM Topics
                         JOIN Topics_TestSettings ON idTopic = fkTopic
                         JOIN TestSettings ON idTestSetting = fkTestSetting
                     WHERE
                         idTestSetting = '".$data[0]."'";
            $this->execQuery($query);
        }
        catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qShowQuestionsForSetting
     * @param   $idTestSetting      String        Requested Test Settings ID
     * @return  Boolean
     * @descr   Returns true if info was saved successfully, false otherwise
     */
    public function qShowQuestionsForSetting($idTestSetting){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $data = $this->prepareData(array($idTestSetting));
            $query= "SELECT idQuestion, status, translation, type, difficulty, fkLanguage, name, fkTopic
                     FROM Questions, TranslationQuestions, Topics
                     WHERE
                        idTopic = fkTopic
                        AND
                        idQuestion=fkQuestion
                        AND
                        idQuestion IN (	SELECT Questions_TestSettings.fkQuestion
                                        FROM Questions_TestSettings
                                        WHERE fkTestSetting = '".$data[0]."')";
            $this->execQuery($query);
        }
        catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qUpdateTestSettingsInfo
     * @param   $idTestSetting          String          Requested Test Settings ID
     * @param   $completeUpdate         String          Type of update
     * @param   $name                   String          Test setting's name
     * @param   $desc                   String          Test setting's description
     * @param   $scoreType              String          Test setting's score type
     * @param   $scoreMin               String          Test setting's minimum score
     * @param   $bonus                  String          Test setting's bonus
     * @param   $negative               String          Test setting's negative
     * @param   $editable               String          Test setting's editable
     * @param   $duration               String          Test setting's duration
     * @param   $questions              String          Test setting's questions number
     * @param   $distributionMatrix     Array           Test setting's random questions distribution for topic and difficulty
     * @param   $questionsT             Array           Test setting's questions topic distribution
     * @param   $questionsD             Array           Test setting's questions difficulty distribution
     * @param   $questionsM             Array           Test setting's mandatory questions
     * @return  Boolean
     * @descr   Returns true if info was saved successfully, false otherwise
     */
    public function qUpdateTestSettingsInfo($idTestSetting, $completeUpdate, $name, $desc, $scoreType=null, $scoreMin=null,
                                            $bonus=null, $negative=null, $editable=null, $duration=null, $questions=null,
                                            $distributionMatrix=null, $questionsT=null, $questionsD=null, $questionsM=null){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $data = $this->prepareData(array($name, $desc, $negative, $editable));
            if($completeUpdate != 'true'){
                $query = "UPDATE TestSettings
                          SET
                              name = '".$data[0]."',
                              description = '".$data[1]."'
                          WHERE
                              idTestSetting = '$idTestSetting'";
                $this->execQuery($query);
            }else{
                $queries = array();
                $scale = round($scoreType / $questions, 1);
                $query = "UPDATE TestSettings
                          SET
                              name = '$data[0]',
                              description = '$data[1]',
                              questions = '$questions',
                              scoreType = '$scoreType',
                              scoreMin = '$scoreMin',
                              scale = '$scale',
                              bonus = '$bonus',
                              duration = '$duration',
                              negative = '$negative',
                              editable = '$editable',
                              numEasy = '".$questionsD[1]['total']."',
                              numMedium = '".$questionsD[2]['total']."',
                              numHard = '".$questionsD[3]['total']."'
                          WHERE
                              idTestSetting = '$idTestSetting'";
                array_push($queries, $query);

                $query = "DELETE FROM Questions_TestSettings
                          WHERE
                              fkTestSetting = '$idTestSetting'";
                array_push($queries, $query);
                foreach($questionsM as $idQuestion){
                    if($idQuestion != 0){
                        $query = "INSERT INTO Questions_TestSettings (fkQuestion, fkTestSetting)
                                  VALUES ('$idQuestion', '$idTestSetting')";
                        array_push($queries, $query);
                    }
                }

                $query = "DELETE FROM Topics_TestSettings
                          WHERE
                              fkTestSetting = '$idTestSetting'";
                array_push($queries, $query);
                foreach($questionsT as $topicID => $arrayQuestionT){
                    if($arrayQuestionT != null){
                        $numEasy = $distributionMatrix[1][$topicID];
                        $numMedium = $distributionMatrix[2][$topicID];
                        $numHard = $distributionMatrix[3][$topicID];
                        $numQuestions = $arrayQuestionT['total'];
                        $query = "INSERT INTO Topics_TestSettings (fkTestSetting, fkTopic, numEasy, numMedium, numHard, numQuestions)
                              VALUES('$idTestSetting', '$topicID', '$numEasy', '$numMedium', '$numHard', '$numQuestions')";
                        array_push($queries, $query);
                    }
                }
//                $log->append(var_export($queries, true));
                $this->execTransaction($queries);
            }

        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qNewSettings
     * @param   $idSubject              String          Test setting's ID
     * @param   $name                   String          Test setting's name
     * @param   $scoreType              String          Test setting's score type
     * @param   $scoreMin               String          Test setting's minimum score
     * @param   $bonus                  String          Test setting's bonus
     * @param   $negative               String          Test setting's negative
     * @param   $editable               String          Test setting's editable
     * @param   $duration               String          Test setting's duration
     * @param   $questions              String          Test setting's questions number
     * @param   $desc                   String          Test setting's description
     * @param   $distributionMatrix     Array           Test setting's random questions distribution for topic and difficulty
     * @param   $questionsT             Array           Test setting's questions topic distribution
     * @param   $questionsD             Array           Test setting's questions difficulty distribution
     * @param   $questionsM             Array           Test setting's mandatory questions
     * @return  Boolean
     * @descr   Returns true if info was saved successfully, false otherwise
     */
    public function qNewSettings($idSubject, $name, $scoreType, $scoreMin, $bonus, $negative, $editable, $duration,
                                 $questions, $desc, $distributionMatrix, $questionsT, $questionsD, $questionsM){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
        	$queries = array();

            $data = $this->prepareData(array($name, $desc));
            $scale = round($scoreType / $questions, 1);
            $query = "INSERT INTO TestSettings (name, description, questions, scoreType, scoreMin, scale, bonus, negative, editable, duration, numEasy, numMedium, numHard, fkSubject)
                  	  VALUES ('$data[0]', '$data[1]', '$questions', '$scoreType', '$scoreMin', '$scale', '$bonus', '$negative', '$editable', '$duration', '".$questionsD[1]['total']."', '".$questionsD[2]['total']."', '".$questionsD[3]['total']."', '$idSubject')";
			array_push($queries, $query);
            $query = "SET @settID = LAST_INSERT_ID()";
            array_push($queries, $query);

            foreach($questionsM as $idQuestion){
                if($idQuestion != 0){
                    $query = "INSERT INTO Questions_TestSettings (fkQuestion, fkTestSetting)
                              VALUES ('$idQuestion', @settID)";
                    array_push($queries, $query);
                }
			}
            foreach($questionsT as $topicID => $arrayQuestionT){
                if($arrayQuestionT != null){
                    $numEasy = $distributionMatrix[1][$topicID];
                    $numMedium = $distributionMatrix[2][$topicID];
                    $numHard = $distributionMatrix[3][$topicID];
                    $numQuestions = $arrayQuestionT['total'];
                    $query = "INSERT INTO Topics_TestSettings (fkTestSetting, fkTopic, numEasy, numMedium, numHard, numQuestions)
                              VALUES(@settID, '$topicID', '$numEasy', '$numMedium', '$numHard', '$numQuestions')";
                    array_push($queries, $query);
                }
            }
            $query = "SELECT @settID";
            array_push($queries, $query);

			//********************************************************
//            $log->append(var_export($queries, true));
            $this->execTransaction($queries);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qDeleteTestSettings
     * @param   $idTestSetting       String        Requested Settings's ID
     * @return  String
     * @descr   Return true if test settings was successfully deleted, false otherwise
     */
    public function qDeleteTestSettings($idTestSetting){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $query = "DELETE FROM TestSettings
                      WHERE
                          idTestSetting = '$idTestSetting'";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

/*******************************************************************
*                             Students                             *
*******************************************************************/

    /**
     * @name    qNewUser
     * @param   $name           String        User's name
     * @param   $surname        String        User's surname
     * @param   $email          String        User's email
     * @param   $token          String        Token's value
     * @param   $role           String        User's role
     * @param   $password       String        User's password
     * @return  Boolean
     * @descr   Returns true if student was successfully created, false otherwise
     */
    public function qNewUser($name, $surname, $email, $token, $role, $password=null){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $data = $this->prepareData(array($name, $surname));
            $queries = array();
            if($password == null){      // Creating a teacher or an admin
                $query = "INSERT INTO Users (name, surname, email, role)
                          VALUES ('$data[0]', '$data[1]', '$email', '$role')";
                array_push($queries, $query);
                $query = "INSERT INTO Tokens (email, action, value)
                          VALUES ('$email', 'c', '$token')";
                array_push($queries, $query);
            }else{                      // Creating a student
                $query = "INSERT INTO Users (name, surname, email, password, role)
                          VALUES ('$data[0]', '$data[1]', '$email', '$password', 's')";
                array_push($queries, $query);
                $query = "SELECT LAST_INSERT_ID()";
                array_push($queries, $query);
            }
            $this->execTransaction($queries);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }
        return $ack;
    }

    /**
     * @name    qNewToken
     * @param   $email       String        User's email
     * @param   $action      String        Token's action
     * @param   $value       String        Token's value
     * @return  Boolean
     * @descr   Returns true if token was successfully created, false otherwise
     */
    public function qNewToken($email, $action, $value){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $queries = array();
            $query = "DELETE
                      FROM Tokens
                      WHERE
                           email = '$email'";
            array_push($queries, $query);
            $query = "INSERT INTO Tokens (email, action, value)
                      VALUES ('$email', '$action', '$value')
                      ON DUPLICATE KEY UPDATE
                          value = '$value'";
            array_push($queries, $query);
            $this->execTransaction($queries);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qUpdateProfile
     * @param   $idUser     String        User's ID
     * @param   $name       String        User's name
     * @param   $surname    String        User's surname
     * @param   $email      String        User's email
     * @param   $password   String        User's password
     * @param   $lang       String        User's language
     * @param   $role       String        User's role
     * @return  Boolean
     * @descr   Returns true if User's profile was successfully updated, false otherwise
     */
    public function qUpdateProfile($idUser, $name=null, $surname=null, $email=null, $password=null, $lang=null, $role = null){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $query = "UPDATE Users
                      SET ";
            if($name != null){
                $data = $this->prepareData(array($name));
                $query .= "name = '$data[0]',";
            }
            if($surname != null){
                $data = $this->prepareData(array($surname));
                $query .= "surname = '$data[0]',";
            }
            if($email != null){
                $query .= "email = '$email',";
            }
            if($password != null){
                $query .= "password = '".$password."',";
            }
            if($lang != null){
                $query .= "fkLanguage = '$lang',";
            }
            if($role != null){
                $query .= "role = '$role',";
            }
            $query = substr_replace($query , '', -1);       // Remove last coma
            $query .= "WHERE
                          idUser = '".$idUser."'";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qTeachers
     * @param   $idSubject          String          Subject's ID
     * @return  Boolean
     * @descr   Returns true if query if successfully executed, false otherwise
     */
    public function qTeachers($idSubject = null){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $query = "SELECT *
                      FROM
                          Users AS U ";
            if($idSubject == null){
                $query .= "WHERE
                               role IN ('at', 't', 'st')";
            }else{
                $query .= " JOIN Users_Subjects AS US ON U.idUser = US.fkUser
                        WHERE
                            role IN ('at', 't', 'st')
                            AND
                            US.fkSubject = '$idSubject';";
            }
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack =false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

/*******************************************************************
*                              Tests                               *
*******************************************************************/

    /**
     * @name    qTestDetails
     * @param   $idSet     Integer        Test set's ID
     * @param   $idTest    Integer        Test's ID
     * @return  Boolean
     * @descr   Search all details about test associated with a specific questions set or specific ID
     */
    public function qTestDetails($idSet, $idTest = null){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $query = "SELECT T.idTest, T.timeStart, T.timeEnd, T.scoreTest, T.scoreFinal, T.status, T.fkSet, T.fkExam, T.bonus AS testBonus,
                             S.idUser, S.name, S.surname, S.email, S.fkLanguage,
                             E.idExam, E.fkSubject,
                             TS.questions, TS.scoreType, TS.scoreMin, TS.scale, TS.bonus, TS.duration, TS.negative, TS.editable
                          FROM
                              Tests AS T
                              JOIN Users AS S ON T.fkUser = S.idUser
                              JOIN Exams AS E ON T.fkExam = E.idExam
                              JOIN TestSettings AS TS ON E.fkTestSetting = TS.idTestSetting
                          WHERE ";
            if($idTest == null){
                $query .= "T.fkSet = '$idSet'";
            }else{
                $query .= "idTest = '$idTest'";
            }
            $log->append($query);
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack =false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qTestList
     * @param   $idUser         Integer        Teacher's ID
     * @return  Boolean
     * @descr   Search all test's details for a teacher
     */
    public function qTestsList($idUser){
        global $log, $user;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $query = "SELECT idTest, timeStart, timeEnd, T.status, scoreTest, fkExam, fkSubject, idUser, S.name, S.surname, Sub.name AS subName
                      FROM Tests AS T
                          JOIN Exams AS E ON T.fkExam = E.idExam
                          JOIN Users AS S ON T.fkUser = S.idUser
                          JOIN Subjects AS Sub ON E.fkSubject = Sub.idSubject
                      WHERE
                          E.fkSubject IN (SELECT fkSubject
                                          FROM Users_Subjects
                                          WHERE
                                              fkUser = '$idUser')";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qUpdateTestStatus
     * @param   $idTest     Integer        Test set's ID
     * @param   $status     String         New test's status
     * @return  Boolean
     * @descr   Return true if status has been successfully updated, false otherwise
     */
    public function qUpdateTestStatus($idTest, $status){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $query = "UPDATE Tests
                      SET status = '$status'
                      WHERE
                          idTest = '$idTest'";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack =false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qStartTest
     * @param   $idTest         String        Test's ID
     * @param   $datetime       String        Start datetime for test
     * @return  Boolean
     * @descr   Return true if successfully set timeStart and status for user test
     */
    public function qStartTest($idTest, $datetime){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $query = "UPDATE Tests
                      SET
                          timeStart = '$datetime',
                          status = 's'
                      WHERE
                          idTest = '$idTest'";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack =false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qUpdateTestAnswers
     * @param   $idSet          String      Questions set ID
     * @param   $questions      Array       Array of all question's ID
     * @param   $answers        Array       Array of all question's answer/s
     * @return  Boolean
     * @descr   Return true if successfully update all answers for requested test
     */
    public function qUpdateTestAnswers($idSet, $questions, $answers){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $query = "UPDATE Sets_Questions SET answer = CASE\n";
            while(count($questions) > 0){
                $question = array_pop($questions);
                $answer = array_pop($answers);
                $query .= "WHEN (fkSet = $idSet AND fkQuestion = $question) THEN '$answer'\n";
            }
            $query .= "ELSE answer
                       END";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack =false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qEndTest
     * @param   $idSet          String      Question set ID
     * @return  Boolean
     * @descr   Return true if test successfully stopped
     */
    public function qEndTest($idSet){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $datetime = date("Y-m-d H:i:s");

            // Get scale and negative from test settings
            $query = "SELECT scale, negative
                      FROM TestSettings AS TS
                          JOIN Exams AS E ON E.fkTestSetting = TS.idTestSetting
                          JOIN Tests AS T ON T.fkExam = E.idExam
                      WHERE
                          T.fkSet = '$idSet'";
            $this->execQuery($query);
            $row = $this->nextRowAssoc();
            $scale = $row['scale'];
            $allowNegative = ($row['negative'] == 0)? false : true;

            // Calculate test's score
            $this->mysqli = $this->connect();
            $score = 0;
            $query = "SELECT idQuestion, type, answer
                      FROM Sets_Questions AS SQ
                           JOIN Questions AS Q ON Q.idQuestion = SQ.fkQuestion
                      WHERE
                          fkSet = '$idSet'";
            $this->execQuery($query);
            $test = $this->getResultAssoc('idQuestion');

            foreach($test as $idQuestion => $setQuestion){
                $question = Question::newQuestion($setQuestion['type'], $setQuestion);
                $scoreTemp = $question->getScoreFromGivenAnswer();
                // If negative score is not allowed and question's score is negative sum 0, sum real score otherwise
                $score2add = (!$allowNegative && $scoreTemp < 0)? 0 : $scoreTemp;
                $score += $score2add;
            }
            $score = round($scale * $score, 2);

            // Update test
            $this->mysqli = $this->connect();
            $query = "UPDATE Tests
                      SET timeEnd = '$datetime',
                          scoreTest = '$score',
                          status = 'e'
                      WHERE
                          fkSet = '$idSet'";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack =false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @param   $idTest             String      Test's ID
     * @param   $correctScores      Array       Test's final score
     * @param   $scoreTest          String      Test's final score
     * @param   $bonus              String      Test's bonus score
     * @param   $scoreFinal         String      Test's final score
     * @param   $scale              Float       Test Setting's scale
     * @param   $allowNegative      Bool        True if test allow negative score, else otherwise
     * @param   $status             String      Test's status (if != 'e')
     * @return  bool
     * @descr   Return true if test successfully archived
     */
    public function qArchiveTest($idTest, $correctScores, $scoreTest, $bonus, $scoreFinal, $scale=1.0, $allowNegative=false, $status='a'){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();
        try{
            $submitted = ($scoreTest == null)? false : true;
            $corrected = (count($correctScores) == 0)? false : true;
            $queries = array();

            if($submitted){
                $query = "SELECT idQuestion, type, answer
                          FROM Sets_Questions AS SQ
                               JOIN Questions AS Q ON Q.idQuestion = SQ.fkQuestion
                          WHERE
                              fkSet = (SELECT fkSet
                                       FROM Tests
                                       WHERE idTest = '$idTest')";
                $this->execQuery($query);
                $test = $this->getResultAssoc('idQuestion');

                if(!$corrected){         // The test is not been corrected, get scores from given answers
                    foreach($test as $idQuestion => $setQuestion){
                        $question = Question::newQuestion($setQuestion['type'], $setQuestion);
                        $scoreTemp = $question->getScoreFromGivenAnswer();
                        // If negative score is not allowed and question's score is negative sum 0, sum real score otherwise
                        $score2add = (!$allowNegative && $scoreTemp < 0)? 0 : $scoreTemp;
                        $correctScores[$idQuestion] = round(($score2add * $scale), 2);
                    }
                }

                $query = "INSERT INTO History(fkTest, fkQuestion, answer, score)
                          VALUES \n";
                foreach($test as $idQuestion => $questionInfo)
                    $query .= "('$idTest', '".$idQuestion."', '".$questionInfo['answer']."', '".$correctScores[$idQuestion]."'),";

                $query = substr_replace($query , '', -1);       // Remove last coma
                array_push($queries, $query);

                $query = "UPDATE Tests
                          SET
                              scoreTest = '$scoreTest',
                              bonus = '$bonus',
                              scoreFinal = '$scoreFinal',
                              status = 'a'
                          WHERE
                              idTest = '$idTest'";
                array_push($queries, $query);
            }else{
                $now = date("Y-m-d H:i:s");
                $query = "UPDATE Tests
                      SET
                          timeEnd = '$now',
                          scoreTest = '0',
                          bonus = '0',
                          scoreFinal = '0',
                          status = '$status'
                      WHERE
                          idTest = '$idTest'";
                array_push($queries, $query);
            }

            $query = "DELETE
                          FROM Sets
                          WHERE
                              idSet = (SELECT fkSet
                                       FROM Tests
                                       WHERE
                                           idTest = '$idTest')";
            array_push($queries, $query);

            $this->mysqli = $this->connect();
            $this->execTransaction($queries);
        }catch (Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__.' : '.$this->getError());
        }

        return $ack;
    }



/*******************************************************************
*                               Sets                               *
*******************************************************************/

    /**
     * @name    qMakeQuestionsSet
     * @param   $idExam     String        Exam's ID
     * @param   $idUser     String        Student's ID
     * @return  Boolean
     * @descr   Create a new test, create a question's set and register student in exam
     */
    public function qMakeQuestionsSet($idExam, $idUser){
        global $log;
        $ack = true;
        $this->error = null;
        $this->mysqli = $this->connect();

        try{
            $query = "SELECT fkTestSetting
                      FROM
                          Exams
                      WHERE
                          idExam = '$idExam'";
            $this->execQuery($query);
            $examInfo = $this->nextRowAssoc();
            $idTestSetting = $examInfo['fkTestSetting'];

            $questionsSelected = array();
            $query = "SELECT *
			 	 	  FROM
			 		      Questions_TestSettings
			 		  WHERE
			 		      fkTestSetting = '$idTestSetting'";
            $this->execQuery($query);
            while(($question = $this->nextRowAssoc())){
                array_push($questionsSelected, $question['fkQuestion']);
            }

//            $log->append("questionsSelected: ".var_export($questionsSelected, true));

            $topics = array();
            $this->mysqli = $this->connect();
            $query= "SELECT *
					 FROM
					     Topics_TestSettings
					 WHERE
					 	 fkTestSetting = '$idTestSetting'";
            $this->execQuery($query);
            while(($topic = $this->nextRowAssoc())){
                $topics[$topic['fkTopic']] = $topic;
            }

//            $log->append("topics: ".var_export($topics, true));

            $questionsSet = $questionsSelected;
            $allQuestions = array();
            foreach($topics as $idTopic => $topicInfo){
                $difficulties = getSystemDifficulties();
                foreach($difficulties as $difficulty => $difficultyName){
                    $difficultyName = 'num'.ucfirst($difficultyName);
                    $this->mysqli = $this->connect();
                    $query = "SELECT idQuestion
                              FROM
                                  Questions
                              WHERE
                                  fkTopic = '$idTopic'
                                  AND
                                  difficulty = '$difficulty'
                                  AND
                                  status = 'a' ";
                    if(count($questionsSelected) > 0)
                        $query .= "AND
                                   idQuestion NOT IN (".implode(',', $questionsSelected).")";
                    $this->execQuery($query);
                    $allQuestions[$idTopic][$difficultyName] = $this->getResultAssoc();

                    $questionsForDifficulty = $topics[$idTopic][$difficultyName];
                    if($questionsForDifficulty <= count($allQuestions[$idTopic][$difficultyName])){
                        while($questionsForDifficulty > 0){
                            $idToAdd = rand(0, (count($allQuestions[$idTopic][$difficultyName]) - 1));
                            array_push($questionsSet, $allQuestions[$idTopic][$difficultyName][$idToAdd]['idQuestion']);
                            unset($allQuestions[$idTopic][$difficultyName][$idToAdd]);
                            $allQuestions[$idTopic][$difficultyName] = array_values($allQuestions[$idTopic][$difficultyName]);

                            $questionsForDifficulty--;
                        }
                    }else{
                        die(ttERegFailedQuestions);
                    }
                }
            }
//            $log->append('$allQuestions: '.var_export($allQuestions, true));
//            $log->append(var_export($questionsSet, true));

            $this->mysqli = $this->connect();
            $queries = array();
            $query = "INSERT INTO Sets (assigned, fkExam)
                      VALUES ('n', '$idExam')";
            array_push($queries, $query);
            $query = "INSERT INTO Sets_Questions (fkSet, fkQuestion, answer)
                      VALUES \n";
            foreach($questionsSet as $idQuestion){
                $query .= "(LAST_INSERT_ID(), '$idQuestion', ''),";
            }
            $query = substr_replace($query , '', -1);       // Remove last coma
            array_push($queries, $query);
            $query = "INSERT INTO Tests (status, fkExam, fkUser)
                      VALUES ('w', '$idExam', '$idUser')";
            array_push($queries, $query);
            $this->execTransaction($queries);

        }catch(Exception $e){
            $ack = false;
            $log->append("Exception: ".$this->getError());
        }

        return $ack;

    }

    /**
     * @name    qAssignSet
     * @param   $idExam        String        Exam's ID
     * @param   $idUser     String        Student's ID
     * @return  Boolean
     * @descr   Return true if set was successfully assigned, false otherwise
     */
    public function qAssignSet($idExam, $idUser){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $queries = array();
            $query = "SELECT idSet
                      FROM Sets
                      WHERE
                          assigned = 'n'
                          AND
                          fkExam = '$idExam'
                      LIMIT 1
                      INTO @setID";
            array_push($queries, $query);
            $query = "UPDATE Sets
                      SET
                          assigned = 'y'
                      WHERE
                          idSet = @setID";
            array_push($queries, $query);
            $query = "UPDATE Tests
                      SET
                          fkSet = @setID
                      WHERE
                          fkExam = '$idExam'
                          AND
                          fkUser = '$idUser'";
            array_push($queries, $query);
            $query = "SELECT @setID";
            array_push($queries, $query);
            $this->execTransaction($queries);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qQuestionSet
     * @param   $idSet          Integer        Test set's ID
     * @param   $idLanguage     Integer        Student preferred language's ID
     * @param   $idSubject      Integer        Subject's ID
     * @return  Boolean
     * @descr   Returns true if questions set was successfully readed, false otherwise
     */
    public function qQuestionSet($idSet, $idLanguage=null, $idSubject=null){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();
        try{
            if($idLanguage != null){
                // Get all the set's questions with student's language
                // UNION
                // All questions with default (subject) language NOT IN previuos group
                $query = "SELECT Q.idQuestion, Q.type, Q.extra, TQ.fkLanguage, TQ.translation, SQ.answer
                          FROM
                              Questions AS Q
                              JOIN TranslationQuestions AS TQ ON Q.idQuestion = TQ.fkQuestion
                              JOIN Sets_Questions AS SQ ON Q.idQuestion = SQ.fkQuestion
                              WHERE
                                  TQ.fkQuestion IN (
                                                  SELECT fkQuestion
                                                  FROM
                                                      Sets_Questions AS SQ
                                                      WHERE
                                                      SQ.fkSet = '$idSet'
                                                  )
                              AND
                              TQ.fkLanguage = '$idLanguage'
                              AND
                              SQ.fkSet = '$idSet'
                          UNION
                          SELECT Q.idQuestion, Q.type, Q.extra, TQ.fkLanguage, TQ.translation, SQ.answer
                          FROM
                              Questions AS Q
                              JOIN TranslationQuestions AS TQ ON Q.idQuestion = TQ.fkQuestion
                              JOIN Sets_Questions AS SQ ON Q.idQuestion = SQ.fkQuestion
                              WHERE
                                  TQ.fkQuestion NOT IN (
                                      SELECT fkQuestion
                                      FROM
                                          TranslationQuestions AS TQ
                                          WHERE
                                              TQ.fkQuestion IN (
                                                              SELECT fkQuestion
                                                              FROM
                                                                  Sets_Questions AS SQ
                                                                  WHERE
                                                                  SQ.fkSet = '$idSet'
                                                              )
                                              AND
                                              TQ.fkLanguage = '$idLanguage'
                                  )
                                  AND
                                  TQ.fkQuestion IN (
                                                  SELECT fkQuestion
                                                  FROM
                                                      Sets_Questions AS SQ
                                                      WHERE
                                                      SQ.fkSet = '$idSet'
                                                  )
                                  AND
                                  TQ.fkLanguage = (SELECT fkLanguage FROM Subjects WHERE idSubject = '$idSubject')
                                  AND
                                  SQ.fkSet = '$idSet'
                          ORDER BY idQuestion";
            }else{
                // Get all the set's questions with default (subject) language
                $query = "SELECT Q.idQuestion, Q.type, Q.type, TQ.translation, SQ.answer
                          FROM
                              Questions AS Q
                              JOIN TranslationQuestions AS TQ ON Q.idQuestion = TQ.fkQuestion
                              JOIN Sets_Questions AS SQ ON Q.idQuestion = SQ.fkQuestion
                              WHERE
                                  TQ.fkQuestion IN (
                                                  SELECT fkQuestion
                                                  FROM
                                                      Sets_Questions AS SQ
                                                      WHERE
                                                      SQ.fkSet = '$idSet'
                                                  )
                              AND
                              SQ.fkSet = '$idSet'\n";
                if($idSubject!=null)
                    $query .= "AND
                               TQ.fkLanguage = (SELECT fkLanguage FROM Subjects WHERE idSubject = '$idSubject')\n";
                $query .= "ORDER BY Q.idQuestion";
            }
            $this->execQuery($query);
        }catch (Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__.' : '.$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qViewArchivedTest
     * @param   $idTest         Integer        Test's ID
     * @param   $idLanguage     Integer        Student preferred language's ID
     * @param   $idSubject      Integer        Subject's ID
     * @return  Boolean
     * @descr   Returns true if questions set was successfully readed, false otherwise
     */
    public function qViewArchivedTest($idTest, $idLanguage = null, $idSubject){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();
        try{
            if($idLanguage != null){
                // Get all the set's questions with student's language
                // UNION
                // All questions with default (subject) language NOT IN previuos group
                $query = "SELECT Q.idQuestion, Q.type, TQ.fkLanguage, TQ.translation, H.answer, H.score
                          FROM
                              History AS H
                              JOIN Questions AS Q ON Q.idQuestion = H.fkQuestion
                              JOIN TranslationQuestions AS TQ ON Q.idQuestion = TQ.fkQuestion
                              WHERE
                                  TQ.fkLanguage = '$idLanguage'
                                  AND
                                  H.fkTest = '$idTest'
                          UNION
                          SELECT Q.idQuestion, Q.type, TQ.fkLanguage, TQ.translation, H.answer, H.score
                          FROM
                              History AS H
                              JOIN Questions AS Q ON Q.idQuestion = H.fkQuestion
                              JOIN TranslationQuestions AS TQ ON Q.idQuestion = TQ.fkQuestion
                              WHERE
                                  H.fkQuestion NOT IN (SELECT fkQuestion
                                                       FROM TranslationQuestions AS TQ
                                                       WHERE
                                                           TQ.fkQuestion IN (SELECT fkQuestion
                                                                             FROM
                                                                                 History AS H
                                                                                 WHERE
                                                                                 H.fkTest = '$idTest')
                                                           AND
                                                           TQ.fkLanguage = '$idLanguage')
                                  AND
                                  TQ.fkLanguage = (SELECT fkLanguage FROM Subjects WHERE idSubject = '$idSubject')
                                  AND
                                  H.fkTest = '$idTest'
                          ORDER BY Q.idQuestion";
            }else{
                // Get all the set's questions with default (subject) language
                $query = "SELECT Q.idQuestion, Q.type, TQ.translation, H.answer, H.score
                          FROM
                              History AS H
                              JOIN Questions AS Q ON Q.idQuestion = H.fkQuestion
                              JOIN TranslationQuestions AS TQ ON Q.idQuestion = TQ.fkQuestion
                              WHERE
                                  TQ.fkLanguage = (SELECT fkLanguage FROM Subjects WHERE idSubject = '$idSubject')
                                  AND
                                  H.fkTest = '$idTest'
                          ORDER BY Q.idQuestion";
            }
            $this->execQuery($query);
        }catch (Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__.' : '.$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qAnswerSet
     * @param   $idQuestion     Integer        Test set's ID
     * @param   $idLanguage     Integer        Student preferred language's ID
     * @param   $idSubject      Integer        Subject's ID
     * @return  Boolean
     * @descr   Returns true if answers set was successfully readed, false otherwise
     */
    public function qAnswerSet($idQuestion, $idLanguage = null, $idSubject){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();
        try{
            if($idLanguage != null){
                // Get all the answers of question with student's language
                // UNION
                // All answers with default (subject) language NOT IN previuos group
                $query = "SELECT *
                          FROM
                              Answers AS A
                              JOIN TranslationAnswers AS TA ON A.idAnswer = TA.fkAnswer
                              WHERE
                                  A.fkQuestion = '$idQuestion'
                              AND
                                  TA.fkLanguage = '$idLanguage'
                          UNION
                          SELECT *
                          FROM
                              Answers AS A
                              JOIN TranslationAnswers AS TA ON A.idAnswer = TA.fkAnswer
                              WHERE
                                  A.idAnswer NOT IN (
                                                    SELECT A.idAnswer
                                                    FROM
                                                        Answers AS A
                                                        JOIN TranslationAnswers AS TA ON A.idAnswer = TA.fkAnswer
                                                        WHERE
                                                            A.fkQuestion = '$idQuestion'
                                                            AND
                                                            TA.fkLanguage = '$idLanguage'
                                                    )
                                  AND
                                  A.fkQuestion = '$idQuestion'
                                  AND
                                  TA.fkLanguage = (SELECT fkLanguage FROM Subjects WHERE idSubject = '$idSubject')
                          ORDER BY idAnswer";
            }else{
                // Get all the answers of question with default (subject) language
                $query = "SELECT *
                          FROM
                              Answers AS A
                              JOIN TranslationAnswers AS TA ON A.idAnswer = TA.fkAnswer
                              WHERE
                                  A.fkQuestion = '$idQuestion'
                              AND
                                  TA.fkLanguage = (SELECT fkLanguage FROM Subjects WHERE idSubject = '$idSubject')
                          ORDER BY A.idAnswer";
            }
            $this->execQuery($query);
        }catch (Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__.' : '.$this->getError());
        }

        return $ack;
    }

/*******************************************************************
*                              Utils                               *
*******************************************************************/

    /**
     * @name    qSelect
     * @param   $tableName      String          Table to search
     * @param   $columnName     String          Field to search
     * @param   $value          String|Array    Value to search
     * @param   $order          String          Value to order by
     * @return  Boolean
     * @descr   Search into a table a specific value for a column
     */
    public function qSelect($tableName, $columnName = '', $value = '', $order = ''){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $newValue = (is_array($value))? implode(',', $value) : $value;

            $data = $this->prepareData(array($tableName, $columnName, $newValue, $order));

            $query = "SELECT * FROM $data[0]";
            if(($columnName != '') && (is_array($value)))
                $query .= " WHERE $data[1] IN ($data[2])";
            elseif(($columnName != '') && ($value != ''))
                $query .= " WHERE $data[1] = '$data[2]'";

            if($order != ''){
                $query .= " ORDER BY $data[3]";
            }
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

    /**
     * @name    qDelete
     * @param   $tableName      String        Table to search
     * @param   $columnName     String        Field to search
     * @param   $value          String        Value to delete
     * @return  Boolean
     * @descr   Deletes a specified row(s) in table
     */
    public function qDelete($tableName, $columnName, $value){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        try{
            $data = $this->prepareData(array($tableName, $columnName, $value));

            $query = "DELETE
                          FROM $data[0]
                      WHERE
                          $data[1] = '$data[2]'";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }

/*******************************************************************
*                            Languages                             *
*******************************************************************/

    /**
     * @name    qGetAllLanguages
     * @return  Array
     * @descr   Returns an associative array for all system languages
     */
    public function qGetAllLanguages(){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        $langs = array();
        try{
            $query = "SELECT *
                      FROM Languages
                      ORDER BY alias";
            $this->execQuery($query);
            while($row = $this->nextRowAssoc()){
                $langs[$row['idLanguage']] = $row['alias'];
            }
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $langs;
    }

    /**
     * @name    qCreateLanguage
     * @param   $alias          String      Language's alias
     * @param   $description    String      Language's description
     * @return  Boolean
     * @descr   Returns true if language was successfully created, false otherwise
     */
    public function qCreateLanguage($alias, $description){
        global $log;
        $ack = true;
        $this->result = null;
        $this->mysqli = $this->connect();

        $langs = array();
        try{
            $data = $this->prepareData(array($alias, $description));
            $query = "INSERT INTO Languages (alias, description)
                      VALUES ('$data[0]', '$data[1]')";
            $this->execQuery($query);
        }catch(Exception $ex){
            $ack = false;
            $log->append(__FUNCTION__." : ".$this->getError());
        }

        return $ack;
    }



/*******************************************************************
*                              mysqli                              *
*******************************************************************/

    /**
     * @name    connect
     * @return  mysqli|null    $mysqli   MySqli   Database connection
     * @descr   Define a database connection
     */
    public function connect() {
        global $log;
        // MySql connection using mysqli object
        $mysqli = null;
        //if (!$this->active) {
        $mysqli = new mysqli($this->dbHost,
            $this->dbUsername,
            $this->dbPassword,
            $this->dbName);
        $mysqli->set_charset("utf8");
        if (mysqli_connect_errno()) {
            $log->append('Connection to MySQL denied');
            die(mysqli_connect_error());
        } else {
            //$log->append('Connection to MySQL succeeded');
            $this->active = true;
        }
        //}
        return $mysqli;
    }

    /**
     * @name    prepareData
     * @param   $data       Array       All data string to prepare
     * @return  Array       $data       String ready
     * @descr   Trim and escape all data to prepare an update query
     */
    private function prepareData($data){
        $index = 0;
        while($index < count($data)){
            $data[$index] = str_replace('"', "'", $data[$index]);
            $data[$index] = trim($this->mysqli->real_escape_string($data[$index]));
            $data[$index] = str_replace("\\\\", "\\", $data[$index]);
            $index++;
        }
        return $data;
    }

    /**
     * @name    execQuery
     * @param   $query      String        Query statement
     * @throws  Exception
     * @descr   Execute a simple query
     */
    private function execQuery($query){
        global $log;
// ******************************************************************* //
//        $log->append($query);
// ******************************************************************* //
        if(!($this->result = $this->mysqli->query($query)))
            throw new Exception("Error");
    }

    /**
     * @name    execTransaction
     * @param   $queries      Array        Array of queries
     * @throws  Exception
     * @descr   Execute a simple query
     */
    private function execTransaction($queries){
        global $log;

        $this->mysqli->autocommit(FALSE);           // Set autocommit to OFF to make a secure transaction
        try{
            while(count($queries) > 0){
                $query = array_shift($queries);
//                $log->append($query);
                $this->execQuery($query);           // Execute queries one by one as long as there isn't error
            }
            $this->mysqli->commit();
        }catch(Exception $ex){
            $ack = false;
            $this->error = $this->getError();
            $this->mysqli->rollback();
            throw new Exception("Error");
        }
        $this->mysqli->autocommit(TRUE);            // Reset autocommit to ON
    }

    /**
     * @name    nextRowAssoc
     * @return  $row     null|Array    Row result
     * @descr   Fetch the next row in result in associative array
     */
    public function nextRowAssoc(){
        $row = null;
        if(($row = $this->result->fetch_assoc()) == null){
//            $this->result->close();
            $this->close();
        }
        return $row;
    }

    /**
     * @name    getResultAssoc
     * @param   $column    String         Column to use as array's index
     * @return  array
     * @descr   Fetch entire result set into associative array
     */
    public function getResultAssoc($column=null){
        global $log;
        $result = array();
        $row = null;
        $index = 0;
        if($column==null)
            while(($row = $this->nextRowAssoc())){
                $result[$index] = $row;
                $index++;
            }
        else{
            while(($row = $this->nextRowAssoc())){
                $result[$row[$column]] = $row;
            }
        }
        return $result;
    }

    /**
     * @name    nextRowEnum
     * @return  $row     null|Array    Row result
     * @descr   Fetch the next row in result in enumerated array
     */
    public function nextRowEnum(){
        $row = null;
        if(($row = $this->result->fetch_row()) == null){
            $this->result->close();
            $this->close();
        }
        return $row;
    }

    /**
     * @name    numResultRows
     * @return  $num        Integer     Number of row
     * @descr   Fetch the row's number in result set
     */
    public function numResultRows(){
        $num = $this->result->num_rows;
        return $num;
    }

    /**
     * @name    numAffectedRows
     * @return  $num        Integer     Number of row
     * @descr   Fetch the affected row's number in previuos MySQL query
     */
    public function numAffectedRows(){
        $num = $this->mysqli->affected_rows;
        return $num;
    }

    /**
     * @name    close
     * @descr   Close the mysqli connection
     */
    public function close(){
        if(isset($this->mysqli)) $this->mysqli->close();
    }

    /**
     * @name    getError
     * @return  null|String     String of last mysqli error
     * @descr   Return last mysqli error if exists
     */
    public function getError(){
        $error = '';
        if(isset($this->mysqli)){
            $error = $this->mysqli->error;
            if($error == ''){
                $error = $this->error;
            }
        }
        return $error;
    }

}
