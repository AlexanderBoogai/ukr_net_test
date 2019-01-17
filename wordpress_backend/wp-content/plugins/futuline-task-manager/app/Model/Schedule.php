<?php namespace Model;

use View\Traits\DateHelper;

class Schedule
{
    use DateHelper;

    protected $db;
    protected $max_t = 2;
    protected $max_t_r = 8;

    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
    }

    public function getTimes($people_count)
    {
        /**
         * Добавить проверку на token !!!
         */
        global $wpdb;

        $recordings_on_day = 0;
        $coun_t = 0;

        $people_count_in_sec = 1200 * $people_count;

        $arr = array();
        for ($i = 1; $i <= 21; $i++){

            $start = date("Y-m-d", strtotime("+ $i day")) . ' 00:00:00';
            $end = date("Y-m-d", strtotime("+ $i day")) . ' 23:59:59';

            $query = "SELECT * FROM `wp_work_schedule` WHERE `work_time_start` BETWEEN '$start' AND '$end' ORDER BY `work_time_start`";
            if  (count($wpdb->get_results($query)) > 0){
                $arr[$i - 1] = $wpdb->get_results($query);
            }
        }

        $arr_times = array();

        // Add times if (+3h from now) records exist

        $today__start = date("Y-m-d", strtotime("today")) . ' 00:00:00';
        $today__end = date("Y-m-d", strtotime("today")) . ' 23:59:59';

        $t__q = "SELECT * FROM `wp_work_schedule` WHERE `work_time_start` BETWEEN '$today__start' AND '$today__end' ORDER BY `work_time_start`";
        $t__r = $wpdb->get_results($t__q);

        // + 3 days START

        if  (count($t__r) > 0){
            $allowable__start__time = date("Y-m-d H:i:s", strtotime("now +3 hours"));
            if  (!(strtotime($allowable__start__time) >= strtotime($today__end))){
                foreach ($t__r as $t__interval){
                    if  (strtotime($allowable__start__time) >= strtotime($t__interval->work_time_end)){
                        continue;
                    } else {
                        $e__q = "SELECT * FROM `wp_user_recordings` WHERE `record_end` BETWEEN '$t__interval->work_time_start' AND '$t__interval->work_time_end' ORDER BY `record_end`";
                        $e__r = $wpdb->get_results($e__q);
                        if  (count($e__r) > 0){
                            // последнее время в этом цыкле
                            $end_record_in_work_interval = $e__r[count($e__r) - 1]->record_end;
                            if (strtotime($allowable__start__time) > strtotime($end_record_in_work_interval)) {
                                break;
                            } else {
                                if  ((strtotime($t__interval->work_time_end) - strtotime($end_record_in_work_interval)) >= $people_count_in_sec){
                                        $arr_times[$coun_t]['start'] = date("Y-m-d H:i", strtotime($end_record_in_work_interval));
                                        $arr_times[$coun_t]['end'] = date("Y-m-d H:i", strtotime($end_record_in_work_interval) + $people_count_in_sec);
                                        $recordings_on_day++;
                                        $coun_t++;
                                }
                            }
                        }
                        break;
                    }
                }
            }
        }

        // + 3 days END

        foreach ($arr as $key => $item) {
            $start_interval_arr = array();
            for ($t = 0; $t < count($item); $t++) {          // Перебираем каждый рабочий интервал
                $er1 = $item[$t]->work_time_start;
                $er2 = $item[$t]->work_time_end;
                $qu1 = "SELECT * FROM `wp_user_recordings` WHERE `record_start` BETWEEN '$er1' AND '$er2' ORDER BY `record_start`";
                if (count($wpdb->get_results($qu1)) > 0){
                    $start_interval_arr[$t] = true;
                } else {
                    $start_interval_arr[$t] = false;
                }
            }

            $recordings_on_day = 0;

            if (count($item) > 0){
                for ($u = 0; $u < count($item); $u++){
                    // Проверка приоритетности диапазона
                    if  ($start_interval_arr[$u]){
                        if  ($recordings_on_day >= $this->max_t || $coun_t >= $this->max_t_r) {
                            break;
                        }
                        $test1 = $item[$u]->work_time_start;
                        $test2 = $item[$u]->work_time_end;
                        $qu = "SELECT * FROM `wp_user_recordings` WHERE `record_start` BETWEEN '$test1' AND '$test2' ORDER BY `record_start`";
                        $rowi = $wpdb->get_results($qu);

                        if (count($rowi) > 0){
                            for ($o = 0; $o < count($rowi); $o++){
                                if  ($recordings_on_day >= $this->max_t || $coun_t >= $this->max_t_r) {
                                    break;
                                }
                                if  ($o === count($rowi) - 1) {
                                    // П О С Л Е Д Н И Й    Д И А П А З О Н
                                    $diff = strtotime($test2) - strtotime($rowi[$o]->record_end);
                                    if  ($diff >= $people_count_in_sec){
                                        if  ($arr_times[$coun_t - 1]['start'] !== date("Y-m-d H:i", strtotime($rowi[$o]->record_end))) {
                                            $recordings_on_day++;
                                            $arr_times[$coun_t]['start'] = date("Y-m-d H:i", strtotime($rowi[$o]->record_end));
                                            $arr_times[$coun_t]['end'] = date("Y-m-d H:i", strtotime($rowi[$o]->record_end) + $people_count_in_sec);
                                            $coun_t++;
                                        }
                                    }
                                } elseif ($o === 0) {
                                    // П Е Р В Ы Й    Д И А П А З О Н
                                    $diff = strtotime($rowi[$o]->record_start) - strtotime($test1);
                                    if  ($diff >= $people_count_in_sec){
                                        if  ($arr_times[$coun_t - 1]['start'] !== date("Y-m-d H:i", strtotime($rowi[$o]->record_start) - $people_count_in_sec)){
                                            $recordings_on_day++;
                                            $arr_times[$coun_t]['start'] = date("Y-m-d H:i", strtotime($rowi[$o]->record_start) - $people_count_in_sec);
                                            $arr_times[$coun_t]['end'] = date("Y-m-d H:i", strtotime($rowi[$o]->record_start));
                                            $coun_t++;
                                        }
                                    }
                                    if  ($recordings_on_day >= $this->max_t || $coun_t >= $this->max_t_r) {
                                        break;
                                    }
                                    if  (count($rowi) >= 1){
                                        $diff = strtotime($rowi[$o + 1]->record_start) - strtotime($rowi[$o]->record_end);
                                        if  ($diff >= $people_count_in_sec){
                                            if  ($arr_times[$coun_t - 1]['start'] !== date("Y-m-d H:i", strtotime($rowi[$o]->record_end))){
                                                $recordings_on_day++;
                                                $arr_times[$coun_t]['start'] = date("Y-m-d H:i", strtotime($rowi[$o]->record_end));
                                                $arr_times[$coun_t]['end'] = date("Y-m-d H:i", strtotime($rowi[$o]->record_end) + $people_count_in_sec);
                                                $coun_t++;
                                            }
                                        }
                                    }
                                } else {
                                    // М Е Ж Д У    Д И А П А З О Н А М И
                                    $diff = strtotime($rowi[$o]->record_start) - strtotime($rowi[$o - 1]->record_end);
                                    if  ($diff >= $people_count_in_sec) {
                                        if  ($arr_times[$coun_t - 1]['start'] !== date("Y-m-d H:i", strtotime($rowi[$o]->record_start) - $people_count_in_sec)) {
                                            $recordings_on_day++;
                                            $arr_times[$coun_t]['start'] = date("Y-m-d H:i", strtotime($rowi[$o]->record_start) - $people_count_in_sec);
                                            $arr_times[$coun_t]['end'] = date("Y-m-d H:i", strtotime($rowi[$o]->record_start));
                                            $coun_t++;
                                        }
                                    }
                                    if  ($recordings_on_day >= $this->max_t || $coun_t >= $this->max_t_r) {
                                        break;
                                    }
                                    $diff = strtotime($rowi[$o]->record_end) - strtotime($rowi[$o + 1]->record_start);
                                    if  ($diff >= $people_count_in_sec){
                                        if  ($arr_times[$coun_t - 1]['start'] !== date("Y-m-d H:i", strtotime($rowi[$o]->record_end))) {
                                            $recordings_on_day++;
                                            $arr_times[$coun_t]['start'] = date("Y-m-d H:i", strtotime($rowi[$o]->record_end));
                                            $arr_times[$coun_t]['end'] = date("Y-m-d H:i", strtotime($rowi[$o]->record_end) + $people_count_in_sec);
                                            $coun_t++;
                                        }
                                    }
                                }
                            }
                        } else {
                            $rr = array();
                            for ($y = 0, $g = strtotime($test1); strtotime($test2) >= $g + $people_count_in_sec; $g += $people_count_in_sec, $y++){
                                $rr[$y]["start"] = date("Y-m-d H:i", $g);
                                $rr[$y]["end"] = date("Y-m-d H:i", $g + $people_count_in_sec);
                            }
                            if  (count($rr) > 0){
                                shuffle($rr);
                                if  (count($rr) === 1){
                                    $arr_times[$coun_t]['start'] = $rr[0]["start"];
                                    $arr_times[$coun_t]['end'] = $rr[0]["end"];
                                    $coun_t++;
                                    $recordings_on_day++;
                                } else {
                                    for ($tr = 0; $recordings_on_day < $this->max_t; $recordings_on_day++, $tr++){
                                        $arr_times[$coun_t]['start'] = $rr[$tr]["start"];
                                        $arr_times[$coun_t]['end'] = $rr[$tr]["end"];
                                        $coun_t++;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if (count($item) > 0){
                for ($u = 0; $u < count($item); $u++){
                    // Проверка приоритетности диапазона
                    if  (!$start_interval_arr[$u]){
                        if  ($recordings_on_day >= $this->max_t || $coun_t >= $this->max_t_r) {
                            break;
                        }
                        $test1 = $item[$u]->work_time_start;
                        $test2 = $item[$u]->work_time_end;
                        $qu = "SELECT * FROM `wp_user_recordings` WHERE `record_start` BETWEEN '$test1' AND '$test2' ORDER BY `record_start`";
                        $rowi = $wpdb->get_results($qu);

                        if (count($rowi) > 0){
                            for ($o = 0; $o < count($rowi); $o++){
                                if  ($recordings_on_day >= $this->max_t || $coun_t >= $this->max_t_r) {
                                    break;
                                }
                                if  ($o === count($rowi) - 1) {
                                    // П О С Л Е Д Н И Й    Д И А П А З О Н
                                    $diff = strtotime($test2) - strtotime($rowi[$o]->record_end);
                                    if  ($diff >= $people_count_in_sec){
                                        if  ($arr_times[$coun_t - 1]['start'] !== date("Y-m-d H:i", strtotime($rowi[$o]->record_end))) {
                                            $recordings_on_day++;
                                            $arr_times[$coun_t]['start'] = date("Y-m-d H:i", strtotime($rowi[$o]->record_end));
                                            $arr_times[$coun_t]['end'] = date("Y-m-d H:i", strtotime($rowi[$o]->record_end) + $people_count_in_sec);
                                            $coun_t++;
                                        }
                                    }
                                } elseif ($o === 0) {
                                    // П Е Р В Ы Й    Д И А П А З О Н
                                    $diff = strtotime($rowi[$o]->record_start) - strtotime($test1);
                                    if  ($diff >= $people_count_in_sec){
                                        if  ($arr_times[$coun_t - 1]['start'] !== date("Y-m-d H:i", strtotime($rowi[$o]->record_start) - $people_count_in_sec)){
                                            $recordings_on_day++;
                                            $arr_times[$coun_t]['start'] = date("Y-m-d H:i", strtotime($rowi[$o]->record_start) - $people_count_in_sec);
                                            $arr_times[$coun_t]['end'] = date("Y-m-d H:i", strtotime($rowi[$o]->record_start));
                                            $coun_t++;
                                        }
                                    }
                                    if  ($recordings_on_day >= $this->max_t || $coun_t >= $this->max_t_r) {
                                        break;
                                    }
                                    if  (count($rowi) >= 1){
                                        $diff = strtotime($rowi[$o + 1]->record_start) - strtotime($rowi[$o]->record_end);
                                        if  ($diff >= $people_count_in_sec){
                                            if  ($arr_times[$coun_t - 1]['start'] !== date("Y-m-d H:i", strtotime($rowi[$o]->record_end))){
                                                $recordings_on_day++;
                                                $arr_times[$coun_t]['start'] = date("Y-m-d H:i", strtotime($rowi[$o]->record_end));
                                                $arr_times[$coun_t]['end'] = date("Y-m-d H:i", strtotime($rowi[$o]->record_end) + $people_count_in_sec);
                                                $coun_t++;
                                            }
                                        }
                                    }
                                } else {
                                    // М Е Ж Д У    Д И А П А З О Н А М И
                                    $diff = strtotime($rowi[$o]->record_start) - strtotime($rowi[$o - 1]->record_end);
                                    if  ($diff >= $people_count_in_sec) {
                                        if  ($arr_times[$coun_t - 1]['start'] !== date("Y-m-d H:i", strtotime($rowi[$o]->record_start) - $people_count_in_sec)) {
                                            $recordings_on_day++;
                                            $arr_times[$coun_t]['start'] = date("Y-m-d H:i", strtotime($rowi[$o]->record_start) - $people_count_in_sec);
                                            $arr_times[$coun_t]['end'] = date("Y-m-d H:i", strtotime($rowi[$o]->record_start));
                                            $coun_t++;
                                        }
                                    }
                                    if  ($recordings_on_day >= $this->max_t || $coun_t >= $this->max_t_r) {
                                        break;
                                    }
                                    $diff = strtotime($rowi[$o]->record_end) - strtotime($rowi[$o + 1]->record_start);
                                    if  ($diff >= $people_count_in_sec){
                                        if  ($arr_times[$coun_t - 1]['start'] !== date("Y-m-d H:i", strtotime($rowi[$o]->record_end))) {
                                            $recordings_on_day++;
                                            $arr_times[$coun_t]['start'] = date("Y-m-d H:i", strtotime($rowi[$o]->record_end));
                                            $arr_times[$coun_t]['end'] = date("Y-m-d H:i", strtotime($rowi[$o]->record_end) + $people_count_in_sec);
                                            $coun_t++;
                                        }
                                    }
                                }
                            }
                        } else {
                            $rr = array();
                            for ($y = 0, $g = strtotime($test1); strtotime($test2) >= $g + $people_count_in_sec; $g += $people_count_in_sec, $y++){
                                $rr[$y]["start"] = date("Y-m-d H:i", $g);
                                $rr[$y]["end"] = date("Y-m-d H:i", $g + $people_count_in_sec);
                            }
                            if  (count($rr) > 0){
                                shuffle($rr);
                                if  (count($rr) === 1){
                                    $arr_times[$coun_t]['start'] = $rr[0]["start"];
                                    $arr_times[$coun_t]['end'] = $rr[0]["end"];
                                    $coun_t++;
                                    $recordings_on_day++;
                                } else {
                                    for ($tr = 0; $recordings_on_day < $this->max_t; $recordings_on_day++, $tr++){
                                        $arr_times[$coun_t]['start'] = $rr[$tr]["start"];
                                        $arr_times[$coun_t]['end'] = $rr[$tr]["end"];
                                        $coun_t++;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        array_multisort($arr_times);
        return $arr_times;
    }

    public function getUserByToken($token)
    {
        $query = "SELECT * FROM `wp_user_recordings` WHERE `token` = '$token'";
        $result = $this->db->get_row($query);
        return $result;
    }

    public function deleteRecordByID()
    {
        $where = array('id' => $_POST['id']);
        $this->db->delete("wp_user_recordings", $where);
        return true;
    }

    public function getInfoRecord()
    {
        $id = $_POST['id'];
        $query = "SELECT * FROM `wp_user_recordings` WHERE `id` = '$id'";
        $result = $this->db->get_row($query);
        $result->day__start = date("Y-m-d", strtotime($result->record_start));
        $result->time__start = date("H:i", strtotime($result->record_start));
        return $result;
    }

    public function registerNewUser($post)
    {
        $new_data = array();
        $name = htmlspecialchars(trim($post["name"]));
        if (empty($name)){
            $new_data['name'] = "Аноним";
        } else {
            $new_data['name'] = htmlspecialchars(trim($post["name"]));
        }
        $new_data['email'] = htmlspecialchars(trim($post["email"]));
        $new_data['phone'] = htmlspecialchars(trim(preg_replace("/[^0-9]/", '', $post["phone"])));

        $new_data['token'] = wp_generate_password(16, false);

        $add_event = "INSERT INTO `wp_user_recordings` (
                                  `id` ,
                                  `name` ,
                                  `phone` ,
                                  `email` ,
                                  `people_count` ,
                                  `token` ,
                                  `record_start`,
                                  `record_end`,
                                  `created_at`) VALUES (
                                  NULL ,
                                  '" . $new_data['name'] . "',
                                  '" . $new_data['phone'] . "',
                                  '" . $new_data['email'] . "',
                                  NULL,
                                  '" . $new_data['token'] . "',
                                  NULL,
                                  NULL,
                                  CURRENT_TIMESTAMP())";
        $this->db->query($add_event);

        setcookie ("token", $new_data['token'], time() + 3600, "/");

        return array('last_i_id' => $this->db->insert_id);
    }

    public function StepTwoValidation($post)
    {
        $arr = array();

        $post["people"] = (integer) htmlspecialchars(trim($post["people"]));

        if ($post["people"] < 1 || $post["people"] > 5){
            $arr['errors']['people'] = "Введите от 1 до 5";
        }

        return $arr;
    }

    public function updateUserSt2($post)
    {
        $new_data['people_count'] = (integer) htmlspecialchars(trim($post["people"]));

        $where = array('token' => $_COOKIE['token']);
        $this->db->update("wp_user_recordings", $new_data, $where);
        return array('last_i_id' => $this->db->insert_id);
    }

    public function registerNewValidation($post)
    {
        $arr = array();

        $post["name"] = htmlspecialchars(trim($post["name"]));
        $post["email"] = htmlspecialchars(trim($post["email"]));
        $post["phone"] = htmlspecialchars(trim(preg_replace("/[^0-9]/", '', $post["phone"])));

        if  (!isset($arr['errors']['name'])) {
            if ((integer)strlen($post["name"]) > 255) {
                $arr['errors']['name'] = "Введите менее 255 символов";
            }
        }

        if (empty($post["phone"])){
            $arr['errors']['phone'] = "Поле обязательно";
        } else {
            if  (!isset($arr['errors']['phone'])) {
                if  ((integer) strlen($post["phone"]) > 12){
                    $arr['errors']['phone'] = "Поле обязательно";
                }
            }
        }

        if (empty($post["email"])){
            $arr['errors']['email'] = "Поле обязательно";
        } else {
            if  (!isset($arr['errors']['email'])) {
                if (!filter_var($post["email"], FILTER_VALIDATE_EMAIL)) {
                    $arr['errors']['email'] = "Введите Email";
                }
            }
            if  (!isset($arr['errors']['email'])) {
                if  ((integer) strlen($post["email"]) > 255){
                    $arr['errors']['email'] = "Введите менее 255 символов";
                }
            }
        }

        return $arr;
    }

    public function updateAdminValidation($post)
    {
//        dd($post);
        $arr = array();

        $post["name"] = htmlspecialchars(trim($post["name"]));
        $post["email"] = htmlspecialchars(trim($post["email"]));
        $post["phone"] = htmlspecialchars(trim(preg_replace("/[^0-9]/", '', $post["phone"])));
        $post["people"] = (integer) htmlspecialchars(trim($post["people"]));

        if (empty($post["name"])){
            $arr['errors']['name'] = "Поле обязательно";
        } else {
            if  (!isset($arr['errors']['name'])) {
                if ((integer)strlen($post["name"]) > 255) {
                    $arr['errors']['name'] = "Введите менее 255 символов";
                }
            }
        }

        if (empty($post["phone"])){
            $arr['errors']['phone'] = "Поле обязательно";
        } else {
            if  (!isset($arr['errors']['phone'])) {
                if  ((integer) strlen($post["phone"]) > 12){
                    $arr['errors']['phone'] = "Поле обязательно";
                }
            }
        }

        if (empty($post["email"])){
            $arr['errors']['email'] = "Поле обязательно";
        } else {
            if  (!isset($arr['errors']['email'])) {
                if (!filter_var($post["email"], FILTER_VALIDATE_EMAIL)) {
                    $arr['errors']['email'] = "Введите Email";
                }
            }
            if  (!isset($arr['errors']['email'])) {
                if  ((integer) strlen($post["email"]) > 255){
                    $arr['errors']['email'] = "Введите менее 255 символов";
                }
            }
        }

        if ($post["people"] < 1 || $post["people"] > 5){
            $arr['errors']['people'] = "Введите от 1 до 5";
        }

        if  (strtotime($post["time"]) % 1200 !== 0){
            $arr['errors']['time'] = "Укажите время кратное 20 мин";
        }

        if (empty($arr["errors"])){
            $start = $post['day'] . ' 00:00:00';
            $end = $post['day'] . ' 23:59:59';
            $check_day = $post['day'] . ' ' . $post['time'] . ':00'; // новое время ::ОТ
            $dateFrom = date("Y-m-d H:i:s", strtotime($check_day) + 1200 * $post["people"]);

            $q = "SELECT * FROM `wp_work_schedule` WHERE `work_time_start` BETWEEN '$start' AND '$end' ORDER BY `work_time_start`";
            $r = $this->db->get_results($q);

            $InRange = false;
            if  (count($r) > 0){
                foreach ($r as $w_Interval){
                    if  (strtotime($check_day) >= strtotime($w_Interval->work_time_start) && strtotime($dateFrom) <= strtotime($w_Interval->work_time_end)){
                        $InRange = true;
                        break;
                    }
                }
            }

            if  ($InRange) {
                $st = $check_day;
                $en = date("Y-m-d H:i:s", strtotime($dateFrom) - 1);
                $qu = "SELECT * FROM `wp_user_recordings` WHERE `record_start` BETWEEN '$st' AND '$en' ORDER BY `record_start`";
                $rowi = $this->db->get_results($qu);
                if  (count($rowi) > 1){
                    $arr['errors']['time'] = "На данное время уже имеются записи";
                } else {
                    if  (count($rowi) === 1){
                        if  ($rowi[0]->id !== $post['recordID']){
                            $arr['errors']['time'] = "На данное время уже имеются записи";
                        }
                    }
                }
            } else {
                $arr['errors']['time'] = "Укажите время в рабочем диапазоне";
            }
        }


        if (empty($arr["errors"])){

            $date__1 = $post['day'] . ' ' . $post['time'] . ':00'; // новое время ::ОТ
            $date__2 = date("Y-m-d H:i:s", strtotime($date__1) + 1200 * $post["people"]);
            $arr = array(
                "name"          => $post['name'],
                "phone"         => $post['phone'],
                "email"         => $post['email'],
                "people_count"  => $post['people'],
                "record_start"  => $date__1,
                "record_end"    => $date__2
            );

            $where = array('id' => $post['recordID']);
            $this->db->update("wp_user_recordings", $arr, $where);
        }

        return $arr;
    }

    public function saveWorkIntervals($post)
    {
        array_multisort($post['work_time_start'], $post['work_time_end']);

        $arr = array();
        for($i = 0; $i < count($post['work_time_start']); $i++){
            if  (empty($post['work_time_start'][$i]) || empty($post['work_time_end'][$i])){
                return array('success' => 0, 'message' => 'Заполните все поля');
            }
            $arr[$i][0] = $post['work_time_start'][$i];
            $arr[$i][1] = $post['work_time_end'][$i];
        }

        // Проверка времени кратному 20 min
        foreach ($arr as $key => $a){
            if  (strtotime($a[0]) % 1200 !== 0 || strtotime($a[1]) % 1200 !== 0){
                return array('success' => 0, 'message' => 'Все поля должны быть кратные 20 мин');
            }
        }

        // Проверка валидного диапазона От - До
        foreach ($arr as $key => $a){
            $is_fail = strtotime($a[0]) < strtotime($a[1]) ? false : true;
            if  ($is_fail === true){
                return array('success' => 0, 'message' => 'Укажите правильные промежутки времени');
            }
        }

        // Проверка валидного диапазона разных промежутков времени (Интервалы)
        foreach ($arr as $key => $a){
            if  ($key + 1 < count($arr)){
                $is_fail = strtotime($a[1]) < strtotime($arr[$key + 1][0]) ? false : true;
                if  ($is_fail === true){
                    return array('success' => 0, 'message' => 'Промежутки времени налаживаются друг на друга');
                }
            }
        }

        if  (count($arr) > 0){
            $validInt = array();
            foreach ($arr as $key => $a){
                if (count($arr) === 1){
                    $validInt[0]['start'] = '00:00:01';
                    $validInt[0]['end'] = date("H:i:s", strtotime($a[0] . ':00') - 1);
                    $validInt[1]['start'] = date("H:i:s", strtotime($a[1] . ':00') + 1);
                    $validInt[1]['end'] = '23:59:59';
                    break;
                } else {
                    if  ($key === 0){
                        $validInt[$key]['start'] = '00:00:01';
                        $validInt[$key]['end'] = date("H:i:s", strtotime($a[0] . ':00') - 1);
                        $validInt[$key + 1]['start'] = date("H:i:s", strtotime($a[1] . ':00') + 1);
                    } elseif ($key === (count($arr) - 1)) {
                        $validInt[$key]['end'] = date("H:i:s", strtotime($a[0] . ':00') - 1);
                        $validInt[$key + 1]['start'] = date("H:i:s", strtotime($a[1] . ':00') + 1);
                        $validInt[$key + 1]['end'] = '23:59:59';
                    } else {
                        $validInt[$key]['end'] = date("H:i:s", strtotime($a[0] . ':00') - 1);
                        $validInt[$key + 1]['start'] = date("H:i:s", strtotime($a[1] . ':00') + 1);
                    }
                }
            }
        }


        if  (count($arr) > 0){
            foreach($validInt as $v){
                $start = $post['date_in'] . ' ' . $v['start'];
                $end = $post['date_in'] . ' ' . $v['end'];
                $q = "SELECT * FROM `wp_user_recordings` WHERE `record_start` BETWEEN '$start' AND '$end' ORDER BY `record_start`";
                $q1 = "SELECT * FROM `wp_user_recordings` WHERE `record_end` BETWEEN '$start' AND '$end' ORDER BY `record_end`";
                if (!is_null($this->db->get_row($q)) || !is_null($this->db->get_row($q1))) {
                    return array('success' => 0, 'message' => 'На выбранное время уже имеются записи');
                }
            }
        }

        $start = $post['date_in'] . ' 00:00:00';
        $end = $post['date_in'] . ' 23:59:59';

        $query = "DELETE FROM `wp_work_schedule` WHERE `work_time_start` BETWEEN '$start' AND '$end' ORDER BY `work_time_start`";
        $this->db->query($query);

        foreach ($arr as $key => $a){
            $start_i = $post['date_in'] . ' ' . $a[0] . ':00';
            $end_i = $post['date_in'] . ' ' . $a[1] . ':00';
            $add_event = "INSERT INTO `wp_work_schedule` (
                                  `id` ,
                                  `work_time_start`,
                                  `work_time_end`) VALUES (
                                  NULL ,
                                  '" . $start_i . "',
                                  '" . $end_i . "')";
            $this->db->query($add_event);
        }

        return array('success' => 1);
    }

    public function getIntervalsForDay($post)
    {
        $start = $post['day'] . ' 00:00:00';
        $end = $post['day'] . ' 23:59:59';

        $query = "SELECT * FROM `wp_work_schedule` WHERE `work_time_start` BETWEEN '$start' AND '$end' ORDER BY `work_time_start`";
        $events = $this->db->get_results($query);

        $arr = array();

        if  (!empty($events)){
            foreach ($events as $key => $e){
                $arr[$key]['start'] = $this->getTimeFromDate($e->work_time_start);
                $arr[$key]['end'] = $this->getTimeFromDate($e->work_time_end);
            }
        }

        return $arr;
    }

    /**
     * @param $param
     * @return int|mixed
     * Вывод названий месяцов для календаря Front
     */
    function get_month($param)
    {
        $month = array(
            1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
            5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
            9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь'
        );

        switch ($param) {
            case 'selected':
                if (isset($_POST["day"])) {
                    $tt = \DateTime::createFromFormat('d.m.y', '01.' . $_POST["day"]);
                    $_get_month = $month[$tt->format("n")];
                } else {
                    $_get_month = $month[date("n", strtotime('first day of this month 00:00:00'))];
                }
                return $_get_month;
                break;
            case 'prev':
                if (isset($_POST["day"])) {
                    $tt = \DateTime::createFromFormat('d.m.y', '01.' . $_POST["day"]);
                    $_get_month = $month[date("n", strtotime("-1 day", strtotime($tt->format("Y-m-01"))))];
                } else {
                    $_get_month = $month[date("n", strtotime("-1 day", strtotime('first day of this month 00:00:00')))];
                }
                return $_get_month;
                break;
            case 'next':
                if (isset($_POST["day"])) {
                    $tt = \DateTime::createFromFormat('d.m.y', '01.' . $_POST["day"]);
                    $_get_month = $month[date("n", strtotime("+1 day", strtotime($tt->format("Y-m-t"))))];
                } else {
                    $_get_month = $month[date("n", strtotime("+1 day", strtotime('last day of this month 23:59:59')))];
                }
                return $_get_month;
                break;
        }

        return 1;
    }

    /**
     * @param $param
     * @return false|int|string
     * Получаем дату в формате (09.17) - (месяц.год) для вывода ссылок календаря
     */
    function get_dot_format_month($param)
    {
        switch ($param) {
            case 'prev':
                if (isset($_POST["day"])) {
                    $tt = \DateTime::createFromFormat('d.m.y', '01.' . $_POST["day"]);
                    $_get_format = date("m.y", strtotime("-1 day", strtotime($tt->format("Y-m-01"))));
                    return $_get_format;
                } else {
                    $start_current_month = date("Y-m-d", strtotime('first day of this month 00:00:00'));
                    $_get_format = date("m.y", strtotime("-1 day", strtotime($start_current_month)));
                    return $_get_format;
                }
            case 'next':
                if (isset($_POST["day"])) {
                    $tt = \DateTime::createFromFormat('d.m.y', '01.' . $_POST["day"]);
                    $_get_format = date("m.y", strtotime("+1 day", strtotime($tt->format("Y-m-t"))));
                    return $_get_format;
                } else {
                    $end_current_month = date("Y-m-d", strtotime('last day of this month 23:59:59'));
                    $_get_format = date("m.y", strtotime("+1 day", strtotime($end_current_month)));
                    return $_get_format;
                }
        }
        return 1;
    }

    /**
     * @param $param
     * @return mixed
     * Возвращяет short_name дня недели
     */
    function get_short_week_name($param)
    {
        $week_days = array(
            0 => 'Вс.',
            1 => 'Пн.',
            2 => 'Вт.',
            3 => 'Ср.',
            4 => 'Чт.',
            5 => 'Пт.',
            6 => 'Сб.');
        $tt = \DateTime::createFromFormat('Y-m-d H:i:s', $param);
        $short = $week_days[$tt->format("w")];
        return $short;
    }

    public function getWorkIntervals($days_in_month)
    {
        $Y_m = $this->getYearMonthForIntervals();
        $daysWorkIntervals = array();

        for ($d = 1; $d <= $days_in_month; $d++){
            if ($d < 10){
                $day = '0' . $d;
            } else {
                $day = $d;
            }
            $start = $Y_m . '-' . $day . ' 00:00:00';
            $end = $Y_m . '-' . $day . ' 23:59:59';

            $query = "SELECT * FROM `wp_work_schedule` WHERE `work_time_start` BETWEEN '$start' AND '$end' ORDER BY `work_time_start`";
            $events = $this->db->get_results($query);

            foreach ($events as $key => $ev){
                $daysWorkIntervals[$d][$key]['work_time_start'] = $ev->work_time_start;
                $daysWorkIntervals[$d][$key]['work_time_end'] = $ev->work_time_end;
            }
        }

        return $daysWorkIntervals;
    }

    public function getYearMonthForIntervals()
    {
        $start = '';
        if (isset($_POST["day"])){
            $tt = \DateTime::createFromFormat('d.m.y', '01.' . $_POST["day"]);
            $start = $tt->format("Y-m");
        } else {
            $start = date("Y-m", strtotime('first day of this month 00:00:00'));
        }

        return $start;
    }

    public function sendEmails($userData)
    {
        add_filter('wp_mail_content_type', create_function('', 'return "text/html";'));

        $message = "<table><tr><td>Имя: </td><td>" . $userData->name . "</td></tr></table>";
        $message .= "<table><tr><td>Телефон: </td><td>" . "+" . substr($userData->phone, 0, 2) . " (" . substr($userData->phone, 2, 3) . ") " . substr($userData->phone, 5, 3) . "-" .substr($userData->phone, 8, 2) . "-" .substr($userData->phone, 10, 2) . "</td></tr></table>";
        $message .= "<table><tr><td>Email: </td><td>" . $userData->email . "</td></tr></table>";
        $message .= "<table><tr><td>Запись на: </td><td>" . $_POST['date'] . ':00' . "</td></tr></table>";
        $message .= "<table><tr><td>Количество человек: </td><td>" . $userData->people_count . "</td></tr></table>";

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Osteopatiya " . '<' . get_option('admin_email') . '>' . "\r\n";

        mail(get_option('admin_email'), '=?utf-8?B?' . base64_encode("Новая запись Остеопатия.укр") . '?=', $message, $headers);
        mail($userData->email, '=?utf-8?B?' . base64_encode("Запись на прием Остеопатия.укр") . '?=', $message, $headers);
    }

    public function updateStep3($userData)
    {
        $new_data = array();

        $user_record_start = $_POST['date'] . ':00';
        $people_count_in_sec = (integer) $userData->people_count * 1200;

        $new_data['record_start'] = $user_record_start;
        $new_data['record_end'] = date("Y-m-d H:i:00", strtotime($user_record_start) + $people_count_in_sec);

        $where = array('id' => $userData->id);
        $this->db->update("wp_user_recordings", $new_data, $where);

        return true;
    }

    public function isRightInterval($count)
    {
        $people_count_in_sec = (integer) $count * 1200;
        $d = \DateTime::createFromFormat('Y-m-d H:i:00', $_POST['date'] . ':00');
        if  ($d){
            if  ($d->format('Y-m-d H:i:s') !== $_POST['date'] . ':00') {
                return false;
            }
        } else {
            return false;
        }

        $start = date("Y-m-d 00:00:00", strtotime($_POST['date']));
        $end = date("Y-m-d 23:59:59", strtotime($_POST['date']));

        $query = "SELECT * FROM `wp_work_schedule` WHERE `work_time_start` BETWEEN '$start' AND '$end' ORDER BY `work_time_start`";
        $arr = $this->db->get_results($query);

        $user_record_start = date("Y-m-d H:i:00", strtotime($_POST['date']));
        $workIntID = false;
        if  (count($arr) > 0){
            for ($i = 0; $i < count($arr); $i++){
                if  (strtotime($arr[$i]->work_time_start) <= strtotime($user_record_start) && (strtotime($user_record_start) + $people_count_in_sec) <= strtotime($arr[$i]->work_time_end)){
                    $workIntID = $i;
                    break;
                }
            }
        }

        if ($workIntID === false){
            return false;
        }

        $user_record_start2 = date("Y-m-d H:i:s", strtotime($_POST['date'] . ':00') + 1);
        $user_record_end = date("Y-m-d H:i:s", (strtotime($user_record_start) + $people_count_in_sec) - 1);

        $query1 = "SELECT * FROM `wp_user_recordings` WHERE `record_start` BETWEEN '$user_record_start2' AND '$user_record_end' ORDER BY `record_start`";
        $arr1 = $this->db->get_row($query1);

        if  (!is_null($arr1)){
            return false;
        }

        return true;
    }

    public function checkToken($withData = false)
    {
        $token = $_COOKIE["token"];
        $query = "SELECT * FROM `wp_user_recordings` WHERE `token` = '$token'";
        $events = $this->db->get_row($query);
        if  ($withData){
            if  (!empty($events)){
                return $events;
            }
            return false;
        } else {
            if  (!empty($events)){
                return true;
            }
            return false;
        }

    }

    public function getDataConfirm()
    {
        $token = $_COOKIE["token"];
        $query = "SELECT * FROM `wp_user_recordings` WHERE `token` = '$token'";
        $events = $this->db->get_row($query);
        return $events;
    }
}