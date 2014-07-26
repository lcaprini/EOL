<?php
/**
 * File: UserController.php
 * User: Masterplan
 * Date: 4/19/13
 * Time: 10:04 AM
 * Desc: Controller for all Admin's operations
 */

class AdminController extends Controller{

    /**
     *  @name   AdminController
     *  @descr  Creates an instance of AdminController class
     */
    public function AdminController(){}

    /**
     * @name    executeAction
     * @param   $action         String      Name of requested action
     * @descr   Executes action (if exists and if user is allowed)
     */
    public function executeAction($action){
        global $user;

        // If have necessary privileges execute action
        if ($this->getAccess($user, $action, $this->accessRules())) {
            $action = 'action'.$action;
            $this->$action();
            // Else, if user is not logged bring him the to login page
        }elseif($user->role == '?'){
            header('Location: index.php?page=login');
            // Otherwise: Access denied
        }else{
            Controller::error('AccessDenied');
        }
    }

    /**
     *  @name   actionIndex
     *  @descr  Shows admin index page
     */
    private function actionIndex(){
        global $engine, $user;

        $user->role = 'a';
        $_SESSION['user'] = serialize($user);

        $engine->renderDoctype();
        $engine->loadLibs();
        $engine->renderHeader();
        $engine->renderPage();
        $engine->renderFooter();
    }

    /**
     *  @name   actionExit
     *  @descr  Exits from Admin, resets Teacher+Admin role and shows (Teacher) index page
     */
    private function actionExit(){
        global $user;

        $db = new sqlDB();
        if($db->qSelect('Users', 'idUser', $user->id)){
            if($row = $db->nextRowAssoc()){
                if($row['role'] == 'at'){
                    $user->role = 'at';
                    $_SESSION['user'] = serialize($user);
                }
                header('Location: index.php');
            }else{
                die(ttEUserNotFound);
            }
        }else{
            die($db->getError());
        }
    }

     /*******************************************************************
     ********************************************************************
     ***                                                              ***
     ***                           Languages                          ***
     ***                                                              ***
     ********************************************************************
     *******************************************************************/

    /**
     *  @name   actionSelectlanguage
     *  @descr  Shows language selection page
     */
    private function actionSelectlanguage(){
        global $engine;

        $engine->renderDoctype();
        $engine->loadLibs();
        $engine->renderHeader();
        $engine->renderPage();
        $engine->renderFooter();
    }

    /**
     *  @name   actionLanguage
     *  @descr  Shows language edit page
     */
    private function actionLanguage(){
        global $engine;

        if(isset($_POST['alias'])){
            $engine->renderDoctype();
            $engine->loadLibs();
            $engine->renderHeader();
            $engine->renderPage();
            $engine->renderFooter();
        }else{
            header('Location: index.php?page=admin/selectlanguage');
        }
    }

    /**
     *  @name   actionSavelanguage
     *  @descr  Saves XML or PHP/Javascript file of requested language
     */
    private function actionSavelanguage(){
        global $log, $config;

        if((isset($_POST['action'])) && (isset($_POST['alias'])) &&
           (isset($_POST['constants'])) && (isset($_POST['translations']))){

            /**
             *  @name   save()
             *  @descr  Common function to save XML language file
             */
            function save(){
                global $log, $config;
                $xml = new DOMDocument();
                $xml->load($config['systemLangsXml'].$_POST['alias'].'.xml');
                $log->append($_POST['constants']);
                $constants = json_decode($_POST['constants'], true);
                $translations = json_decode($_POST['translations'], true);

                for($index = 0; $index < count($constants); $index++)
                    $xml->getElementById($constants[$index])->nodeValue = str_replace("\n", '\n', $translations[$index]);

                chmod($config['systemLangsXml'].$_POST['alias'].'.xml', 0766);
                if($xml->save($config['systemLangsXml'].$_POST['alias'].'.xml'))
                    echo 'ACK';
                else
                    echo 'NACK';
            }

            if($_POST['action'] == 'xml')
                save();
            elseif($_POST['action'] == 'files'){
                save();
                $xml = new DOMDocument();
                $phpText = "<?php\n";
                $jsText = "\n";
                $xml->load($config['systemLangsXml'].$_POST['alias'].'.xml');
                $texts = $xml->getElementsByTagName('text');

                for($index = 0; $index < $texts->length; $index++){
                    $id = explode('_', $texts->item($index)->getAttribute('id'));
                    $value = str_replace('\n', '<br/>', $texts->item($index)->nodeValue);

                    if($id[0] == 'P'){
                        $value = str_replace("'", "\'", $value);
                        $phpText .= "define('$id[1]' , '$value');\n";
                    }elseif($id[0] == 'J')
                        $jsText .= "var $id[1] = \"$value\";\n";
                    elseif($id[0] ==  'A'){
                        $jsText .= "var $id[1] = \"$value\";\n";
                        $value = str_replace("'", "\'", $value);
                        $phpText .= "define('$id[1]' , '$value');\n";
                    }
                }

                $fP = fopen($config['systemLangsDir'].$_POST['alias'].'/lang.php', "w");
                chmod($config['systemLangsDir'].$_POST['alias'].'/lang.php', 0766);
                $fJ = fopen($config['systemLangsDir'].$_POST['alias'].'/lang.js', "w");
                chmod($config['systemLangsDir'].$_POST['alias'].'/lang.js', 0766);

                $write = false;
                $attemps = 3;
                while(($attemps > 0) && (!$write)){
                    if((flock($fP, LOCK_EX)) && (flock($fJ, LOCK_EX))){
                        ftruncate($fP, 0);
                        ftruncate($fJ, 0);
                        fwrite($fP, $phpText);
                        fwrite($fJ, $jsText);
                        fflush($fP);
                        fflush($fJ);
                        flock($fP, LOCK_UN);
                        flock($fJ, LOCK_UN);
                        $write = true;
                    }else{
                        $attemps--;
                        sleep(2);
                    }
                }
                fclose($fP);
                fclose($fJ);

                echo 'ACK';
            }
        }else
            $log->append(__FUNCTION__." : Params not set - ".var_export($_POST));
    }

    /**
     *  @name   actionNewlanguage
     *  @descr  Creates a new XML language file
     */
    private function actionNewlanguage(){
        global $engine, $log, $config;

        if((isset($_POST['description'])) && (isset($_POST['alias']))){

            $alias = strtolower($_POST['alias']);
            $description = ucfirst(strtolower($_POST['description']));

            if(file_exists($config['systemLangsDir'].$alias.'/')){
                echo '0';
            }else{
                $db = new sqlDB();
                if($db->qCreateLanguage($alias, $description)){
                    if((mkdir($config['systemLangsDir'].$alias.'/')) &&
                       (copy($config['systemLangsDir'].'en/lang.php', $config['systemLangsDir'].$alias.'/lang.php')) &&
                       (copy($config['systemLangsDir'].'en/lang.js', $config['systemLangsDir'].$alias.'/lang.js')) &&
                       (copy($config['systemLangsXml'].'en.xml', $config['systemLangsXml'].$alias.'.xml'))){
                        $xml = new DOMDocument();
                        $xml->load($config['systemLangsXml'].$alias.'.xml');
                        $xml->getElementById('alias')->nodeValue = $alias;
                        $xml->getElementById('name')->nodeValue = $description;
                        $xml->save($config['systemLangsXml'].$alias.'.xml');
                        echo 'ACK';
                    }else{
                        unlink($config['systemLangsDir'].$alias.'/lang.php');
                        unlink($config['systemLangsDir'].$alias.'/lang.js');
                        unlink($config['systemLangsXml'].$alias.'.xml');
                        rmdir($config['systemLangsDir'].$alias.'/');
                    }
                }else{
                    echo ttEDatabase;
                }
            }
        }else{
            $engine->renderDoctype();
            $engine->loadLibs();
            $engine->renderHeader();
            $engine->renderPage();
            $engine->renderFooter();
        }
    }

     /*******************************************************************
     ********************************************************************
     ***                                                              ***
     ***                             Rooms                            ***
     ***                                                              ***
     ********************************************************************
     *******************************************************************/

    /**
     *  @name   actionDeleteroom
     *  @descr  Deletes requested room
     */
    private function actionDeleteroom(){
        global $log;

        if(isset($_POST['idRoom'])){
            if($_POST['idRoom'] != '0'){
                $db = new sqlDB();
                if($db->qDeleteRoom($_POST['idRoom'])){
                    if($db->numAffectedRows() > 0){
                        echo 'ACK';
                    }else{
                        die(ttERoomUsed);   // Error: Room used by at least one exam
                    }
                }else{
                    die($db->getError());
                }
            }else{
                die(ttERoomAllDelete);      // Error: 'All' cannot be deleted
            }
        }else{
            $log->append(__FUNCTION__." : Params not set");
        }
    }

    /**
     *  @name   actionNewroom
     *  @descr  Shows page to create a new room
     */
    private function actionNewroom(){
        global $engine;

        if((isset($_POST['name'])) && (isset($_POST['desc'])) &&
            (isset($_POST['ipStart'])) && (isset($_POST['ipEnd']))){

            $db = new sqlDB();
            $ipStart = ip2long($_POST['ipStart']);
            $ipEnd = ip2long($_POST['ipEnd']);
            if($db->qNewRoom($_POST['name'], $_POST['desc'], $ipStart, $ipEnd)){
                echo "ACK";
            }else{
                die($db->getError());
            }
            $db->close();
        }else{
            $engine->renderDoctype();
            $engine->loadLibs();
            $engine->renderHeader();
            $engine->renderPage();
            $engine->renderFooter();
        }
    }

    /**
     *  @name   actionRooms
     *  @descr  Shows rooms edit page
     */
    private function actionRooms(){
        global $engine;

        $engine->renderDoctype();
        $engine->loadLibs();
        $engine->renderHeader();
        $engine->renderPage();
        $engine->renderFooter();
    }

    /**
     *  @name   actionShowroominfo
     *  @descr  Shows info about a room
     */
    private function actionShowroominfo(){
        global $log;

        if(isset($_POST['idRoom'])){
            $db = new sqlDB();
            if($db->qSelect('Rooms', 'idRoom', $_POST['idRoom'])){
                if($row = $db->nextRowEnum()){
                    $row['3'] = long2ip($row['3']);
                    $row['4'] = long2ip($row['4']);
//                     echo json_encode($row, JSON_UNESCAPED_UNICODE);   // For PHP >= 5.4.0
                    $json = str_replace('\\/', '/', json_encode($row));  // Use this for
                    echo $json;                                          // PHP < 5.4.0
                }
            }else{
                echo "NACK";
                $log->append(__FUNCTION__." : ".$db->getError());
            }
        }else{
            $log->append(__FUNCTION__." : Params not set");
        }
    }

    /**
     *  @name   actionUpdateroominfo
     *  @descr  Saves edited informations about a room
     */
    private function actionUpdateroominfo(){
        global $log;

        if((isset($_POST['idRoom'])) &&
            (isset($_POST['name'])) && (isset($_POST['desc'])) &&
            (isset($_POST['ipStart'])) && (isset($_POST['ipEnd']))){
            if($_POST['idRoom'] != '0'){
                $db = new sqlDB();
                $ipStart = ip2long($_POST['ipStart']);
                $ipEnd = ip2long($_POST['ipEnd']);
                if($db->qUpdateRoomInfo($_POST['idRoom'], $_POST['name'], $_POST['desc'], $ipStart, $ipEnd)){
                    if($db->numAffectedRows() > 0){
                        echo "ACK";
                    }else{
                        die(ttERoomUsed);     // Error: Room used by at least one exam
                    }
                }else{
                    die($db->getError());
                }
            }else{
                die(ttEEditRoomAll);          // Error: 'All' cannot be edited
            }
            $db->close();
        }else{
            $log->append(__FUNCTION__." : Params not set");
        }
    }

     /*******************************************************************
     ********************************************************************
     ***                                                              ***
     ***                             Users                            ***
     ***                                                              ***
     ********************************************************************
     *******************************************************************/

    /**
     *  @name   actionLostpassword
     *  @descr  Shows form for reset account password
     */
    private function actionLostpassword(){
        global $log, $config, $engine;
        if(isset($_POST['email'])){
            $db = new sqlDB();
            if(($db->qSelect('Users', 'email', $_POST['email'])) && ($db->numResultRows() != 0)){
                $token = randomPassword(10).strtotime('now');
                $token = sha1($token);
                if($db->qNewToken($_POST['email'], 'p', $token)){
                    $message = str_replace('_LINK_', $config['systemHome'].'index.php?page=admin/setpassword&t='.$token, ttMailLostPassword);
                    $message = str_replace('\n', "\n", $message);
                    mail($_POST['email'], ttResetPassword, $message,'From: '.$config['systemTitle'].' <'.$config['systemEmail'].'>','-f '.$config['systemEmail']);

                    echo 'ACK';
                }
            }else
                die(ttEEmailNotRegistered); // Error: Email not registered
        }else{
            $engine->renderDoctype();
            $engine->loadLibs();
            $engine->renderHeader();
            $engine->renderPage();
            $engine->renderFooter();
        }
    }

    /**
     *  @name   actionNewteacher
     *  @descr  Shows form to add new teacher/teacher-administrator
     */
    private function actionNewteacher(){
        global $log, $config, $engine;

        if((isset($_POST['name'])) && (isset($_POST['surname'])) &&
           (isset($_POST['email'])) && (isset($_POST['role']))){
            $db = new sqlDB();
            if(($db->qSelect('Users', 'email', $_POST['email'])) && ($db->numResultRows() == 0)){
                $token = sha1(randomPassword(10).strtotime('now'));
                if($db->qNewUser($_POST['name'], $_POST['surname'], $_POST['email'], $token, $_POST['role'])){
                    $message = str_replace('_SYSTEMNAME_', $config['systemTitle'], ttMailNewTeacher);
                    $message = str_replace('\n', "\n", $message);
                    $message .= "\n\n".$config['systemHome'].'index.php?page=admin/setpassword&t='.$token;
                    mail($_POST['email'], ttAccountActivation, $message,'From: '.$config['systemTitle'].' <'.$config['systemEmail'].'>','-f '.$config['systemEmail']);

                    echo 'ACK';
                }else{
                    die($db->getError());
                }
            }else{
                die(ttEEmailAlreadyRegistered);   // Error: Email already registered
            }
        }else{
            $engine->renderDoctype();
            $engine->loadLibs();
            $engine->renderHeader();
            $engine->renderPage();
            $engine->renderFooter();
        }
    }

    /**
     *  @name   actionNewstudent
     *  @descr  Shows form to add new student
     */
    private function actionNewstudent(){
        global $log, $config, $engine, $user, $ajaxSeparator;

        if((isset($_POST['name'])) && (isset($_POST['surname'])) &&
           (isset($_POST['email'])) && (isset($_POST['password']))){
            $db = new sqlDB();
            if(($db->qSelect('Users', 'email', $_POST['email'])) && ($db->numResultRows() == 0)){
                if($user->role == '?'){
                    $password = $_POST['password'];
                }else{
                    $password = randomPassword(8);
                }
                if(($db->qNewUser($_POST['name'], $_POST['surname'], $_POST['email'], null, 's', sha1($password))) & ($student = $db->nextRowEnum())){
                    $message = str_replace('_USERNAME_', $_POST['name'], ttMailCredentials);
                    $message = str_replace('_USEREMAIL_', $_POST['email'], $message);
                    $message = str_replace('_USERPASSWORD_', $_POST['password'], $message);
                    $message = str_replace('\n', "\n", $message);
                    mail($_POST['email'], ttAccountActivation, $message,'From: '.$config['systemTitle'].' <'.$config['systemEmail'].'>','-f '.$config['systemEmail']);

                    if($user->role == '?'){
                        if($userInfo = $db->qLogin($_POST['email'], sha1($_POST['password']))){
                            if($userInfo != null){
                                if($config['systemSecure'] == "session")
                                    $_SESSION['logged'] = true;
                                else
                                    setcookie ("logged", true, $config['cookieDeadline'],"/");

                                $_SESSION['user'] = serialize(new User($userInfo));
                            }
                        }else{
                            die($db->getError());
                        }
                    }

                    echo 'ACK'.$ajaxSeparator.$student[0];
                }else{
                    die($db->getError());
                }
            }else{
                die(ttEEmailAlreadyRegistered);   // Error: Email already registered
            }
        }else{
            $engine->renderDoctype();
            $engine->loadLibs();
            $engine->renderHeader();
            $engine->renderPage();
            $engine->renderFooter();
        }
    }

    /**
     *  @name   actionProfile
     *  @descr  Shows profile page of user's account
     */
    private function actionProfile(){
        global $engine;

        $engine->renderDoctype();
        $engine->loadLibs();
        $engine->renderHeader();
        $engine->renderPage();
        $engine->renderFooter();

    }

    /**
     *  @name   actionSetpassword
     *  @descr  Shows page to insert the first password and activate user's account
     *          or sets a new password after reset operation
     */
    private function actionSetpassword(){
        global $log, $engine, $config, $user;

        if(isset($_GET['t'])){
            $engine->renderDoctype();
            $engine->loadLibs();
            $engine->renderHeader();
            $engine->renderPage();
            $engine->renderFooter();
        }elseif((isset($_POST['token'])) && (isset($_POST['password']))){
            $db = new sqlDB();
            if(($db->qSelect('Tokens', 'value', $_POST['token'])) && ($token = $db->nextRowAssoc()) &&
               ($db->qSelect('Users', 'email', $token['email'])) && ($userInfo = $db->nextRowAssoc()) &&
               ($db->qUpdateProfile($userInfo['idUser'], null, null, null, sha1($_POST['password']))) &&
               ($db->qDelete('Tokens', 'value', $_POST['token']))){
                $message = str_replace('_USERNAME_', $userInfo['name'], ttMailCredentials);
                $message = str_replace('_USEREMAIL_', $userInfo['email'], $message);
                $message = str_replace('_USERPASSWORD_', $_POST['password'], $message);
                $message = str_replace('\n', "\n", $message);
                mail($userInfo['email'], ttNewCredentials, $message,'From: '.$config['systemTitle'].' <'.$config['systemEmail'].'>','-f '.$config['systemEmail']);

                if($user->role == '?'){
                    if($userLog = $db->qLogin($userInfo['email'], sha1($_POST['password']))){
                        if($userLog != null){
                            if($config['systemSecure'] == "session")
                                $_SESSION['logged'] = true;
                            else
                                setcookie ("logged", true, $config['cookieDeadline'],"/");
                            $_SESSION['user'] = serialize(new User($userLog));
                        }
                    }else{
                        die($db->getError());
                    }
                }

                echo 'ACK';
            }else{
                die($db->getError());
            }
        }else{
            $log->append(__FUNCTION__." : Params not set");
        }
    }

    /**
     *  @name   actionUpdateprofile
     *  @descr  Updates user's information
     */
    private function actionUpdateprofile(){
        global $user, $log;

        if((isset($_POST['name'])) && (isset($_POST['surname'])) &&
           (isset($_POST['oldPassword'])) && (isset($_POST['newPassword']))){

            $db = new sqlDB();
            $password = null;
            if(($_POST['oldPassword'] != '') && ($_POST['newPassword'] != '')){
                if(($db->qSelect('Users', 'idUser', $user->id)) && ($userInfo = $db->nextRowAssoc())){
                    if($userInfo['password'] == sha1($_POST['oldPassword'])){
                        $password = sha1($_POST['newPassword']);
                    }else{
                        die(ttEOldPasswordWrong);
                    }
                }else{
                    die($db->getError());
                }
            }
            if($db->qUpdateProfile($user->id, $_POST['name'], $_POST['surname'], null, $password)){
                $user->name = $_POST['name'];
                $user->surname = $_POST['surname'];
                $_SESSION['user'] = serialize($user);
                echo 'ACK';
            }else{
                die(ttEDatabase);
            }
        }elseif(isset($_POST['lang'])){
            $db = new sqlDB();
            if($db->qUpdateProfile($user->id, null, null, null, null, $_POST['lang'])){
                if(($db->qSelect('Languages')) && ($allLangs = $db->getResultAssoc('idLanguage'))){
                    $user->lang = $allLangs[$_POST['lang']]['alias'];
                    $_SESSION['user'] = serialize($user);
                    echo 'ACK';
                }else{
                    die(ttEDatabase);
                }
            }else{
                die(ttEDatabase);
            }
        }else{
            $log->append(__FUNCTION__." : Params not set");
        }
    }

    /**
     *  @name   accessRules
     *  @descr  Returns all access rules for User controller's actions:
     *  array(
     *     array(
     *       (allow | deny),                                     Parameter
     *       'actions' => array('*' | 'act1', ['act2', ....]),   Actions
     *       'roles'   => array('*' | '?' | 'a' | 't' | 's')     User's Role
     *     ),
     *  );
     */
    private function accessRules(){
        return array(
            array(
                'allow',
                'actions' => array('Index', 'Exit'),
                'roles'   => array('a'),
            ),
            array(
                'allow',
                'actions' => array('Profile', 'Updateprofile'),
                'roles'   => array('a', 't', 's'),
            ),
            array(
                'allow',
                'actions' => array('Newstudent'),
                'roles'   => array('?', 'a'),
            ),
            array(
                'allow',
                'actions' => array('Newteacher'),
                'roles'   => array('a'),
            ),
            array(
                'allow',
                'actions' => array('Newuserfromemail'),
                'roles'   => array('t'),
            ),
            array(
                'allow',
                'actions' => array('Setpassword', 'Lostpassword'),
                'roles'   => array('?'),
            ),
            array(
                'allow',
                'actions' => array('Rooms', 'Showroominfo', 'Newroom', 'Updateroominfo', 'Deleteroom',
                                   'Selectlanguage', 'Language', 'Savelanguage', 'Newlanguage'),
                'roles'   => array('a'),
            ),
            array(
                'deny',
                'actions' => array('*'),
                'roles'   => array('*'),
            ),
        );
    }
}