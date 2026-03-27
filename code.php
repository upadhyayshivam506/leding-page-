<?php
session_start();
//include('dbconfig.php');
echo '<a href="https://export-excel.topbschoolsinindia.in/">upload excel file</a>';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if(isset($_POST['save_excel_data']))
{
    $college_id=$_POST['college'];
    $fileName = $_FILES['import_file']['name'];
    $file_ext = pathinfo($fileName, PATHINFO_EXTENSION);

    $allowed_ext = ['xls','csv','xlsx'];

    if(in_array($file_ext, $allowed_ext))
    {
        $inputFileNamePath = $_FILES['import_file']['tmp_name'];
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileNamePath);
        $data = $spreadsheet->getActiveSheet()->toArray();

        $count = "0";
        foreach($data as $row)
        {
            if($count > 0)
            {
				$lead_id = $row['0'];
                $name = $row['1'];
                $email = $row['2'];
                $phone_no = $row['3'];
                $course = $row['4'];
				$specialization = $row['5'];
				$college_campus = $row['6'];
                $college_name = $row['7'];
                $city = $row['8'];
                //$subcity = $row['9'];
                //$mode = $row['10'];
				//$source = $row['11'];
				$state = $row['12'];
                /*$country = $row['13'];
                $usr_id = $row['14'];
                $campaign = $row['15'];
                $referrer = $row['16'];
				$course_id = $row['17'];
				$collegeId = $row['18'];
                $lead_type = $row['19'];
                $created_on = $row['20']; 
                $course_fee = $row['21'];
                $college_city = $row['22'];
				$action = $row['23'];
				$exam_name = $row['24'];
				$coaching = $row['25'];*/
			
             /* $studentQuery = "INSERT INTO students_lead (lead_id,name,email,phone_no,course,specialization,college_campus,college_name,city,subcity,mode,source,
			   state,country,usr_id,campaign,referrer,course_id,collegeId,lead_type,created_on,course_fee,college_city,action,exam_name,coaching) 
			   VALUES ('$lead_id','$name','$email','$phone_no','$course','$specialization','$college_campus','$college_name','$city','$subcity','$mode','$source','$state',
			   '$country','$usr_id','$campaign','$referrer','$course_id','$collegeId','$lead_type','$created_on','$course_fee','$college_city','$action','$exam_name','$coaching')";
                $result = mysqli_query($conn, $studentQuery);*/
                $msg = true;
          
     if($college_id == 'IBI'){    
        $data_string ='{ 
        "college_id":"578",
        "name":"'.$row['1'].'",
        "email":"'.$row['2'].'",
        "country_dial_code":"+91",
        "mobile":"'.$row['3'].'",
        "source":"career_mantra",
        "state":"'.$row['12'].'",
        "Campus":"'.$row['6'].'",
        "city":"'.$row['8'].'",
        "Course":"'.$row['4'].'",
        "InstanceDate":"'.$row['20'].'",
        "secret_key":"76f848961f95f47d9a9c7ce4f80d41c9"}';
        
       echo $data_string;
        
 $curl = curl_init('https://api.nopaperforms.com/dataporting/578/career_mantra');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
        echo  $json_response = curl_exec($curl);
          //$json_response = curl_exec($curl);
                  sleep(7);
            }      /*-------------------------------------Sunstone lead start---------------------------------------*/                
    elseif($college_id == 'Sunstone' && !empty($name)){
                $data_string ='{
                "name":"'.$row['1'].'",
                "email":"'.$row['2'].'",
                "mobile":"'.$row['3'].'",
                "program":"'.$row['4'].'",
                "utm_source":"Aff_4074Care",
                "state":"'.$row['12'].'",
                "city":"'.$row['8'].'",
                "campus":""
            }';
    	echo $data_string;	
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_URL, 'https://hub-console-api.sunstone.in/lead/leadPush');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer  cac0713d-076b-4817-b713-af8bc49e5a66'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer cac0713d-076b-4817-b713-af8bc49e5a66',
                'Content-Type: application/json',
            ));
        echo "response: " .  $result = curl_exec($ch);
                  sleep(7);
            }     /*-------------------------------------IILM lead start---------------------------------------*/                
    elseif($college_id == 'IILM'){
                $data_string ='{
                "college_id":"377",
                "name":"'.$row['1'].'",
                "email":"'.$row['2'].'",
                "mobile":"'.$row['3'].'",
                "course":"'.$row['4'].'",
                "source":"career_mantra",
                "state":"'.$row['12'].'",
                "city":"'.$row['8'].'",
                "campus":"'.$row['6'].'", 
                "secret_key":"8a44b9c9743ed51af2795dea2f3bc7e4"
    		}';
    	echo $data_string;	
        $curl = curl_init('https://api.nopaperforms.com/dataporting/377/career_mantra');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
             echo   $json_response = curl_exec($curl);
                 sleep(7);
            }      /*------------------------------------------NITTE lead start----------------------*/
        elseif($college_id == 'NITTE'){
            $data_string ='{
            "college_id":"5609",
            "source":"career_mantra",
            "name":"'.$row['1'].'",
            "email":"'.$row['2'].'",
            "mobile":"'.$row['3'].'",
            "state":"'.$row['12'].'",
            "city":"'.$row['8'].'",
            "course":"'.$row['4'].'",
            "specialization":"'.$row['5'].'",
            "secret_key":"2e27177c6457060315ef5a782df92c16"
		}';
		echo $data_string;	
         $curl = curl_init('https://api.in5.nopaperforms.com/dataporting/5609/career_mantra');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
      echo $json_response = curl_exec($curl);
      sleep(7);

        }/*-------------------------------------------------------------------------------------*/

        elseif($college_id == 'KKMU'){
            $data_string ='{
            "college_id":"692",
            "source":"career_mantra",
            "name":"'.$row['1'].'",
            "email":"'.$row['2'].'",
            "mobile":"'.$row['3'].'",
            "state":"'.$row['12'].'",
            "city":"'.$row['8'].'",
            "campus":"'.$row['6'].'",
            "university_id":"UG",
            "course":"'.$row['4'].'",
            "specialization":"'.$row['5'].'",
            "secret_key":"b5a103f0b5bdfc04851278844c7aa02f"
		}';
		echo $data_string;	
        $curl = curl_init('https://api.nopaperforms.com/dataporting/692/career_mantra');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
           echo $json_response = curl_exec($curl);
            sleep(7);
         } /*-----------------------------------KCM start--------------------------------------------------*/
          elseif($college_id == 'KCM'){
             //echo"test kcm lead";
           $data_string ='{
            "college_id":"434",
            "name":"'.$row['1'].'",
            "email":"'.$row['2'].'",
            "mobile":"'.$row['3'].'",
            "source":"career_mantra",
            "state":"'.$row['12'].'",
            "city":"'.$row['8'].'", 
            "course":"'.$row['4'].'",
            "secret_key":"ee3289b1bec1041abb2415cea1662b24"
		}';
        echo $data_string;
        $curl = curl_init('https://api.nopaperforms.com/dataporting/434/career_mantra');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
            echo  $json_response = curl_exec($curl);
            sleep(7);
         }/*----------------------------------------------ppsu lead start----------------------------------------------------------*/
         elseif($college_id == 'ppsu'){
            $data_string ='{
            "college_id":"5562",
            "name":"'.$row['1'].'",
            "email":"'.$row['2'].'",
            "mobile":"'.$row['3'].'",
            "source":"career_mantra",
            "state":"'.$row['12'].'",
            "city":"'.$row['8'].'",
            "campus":"'.$row['6'].'", 
            "course":"'.$row['4'].'",
            "specialization":"'.$row['5'].'",
            "secret_key":"40a84ffa0a1c0392936d90a998d1c849"
		}';
		echo $data_string;	
        $curl = curl_init('https://api.in5.nopaperforms.com/dataporting/5562/career_mantra');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
           echo $json_response = curl_exec($curl);
            sleep(7);
         }/*---------------------------------GNOIT start-----------------------------------------------*/
         elseif($college_id == 'GNOIT'){
            $data_string ='{ 
        "college_id":"19",
        "name":"'.$row['1'].'",
        "email":"'.$row['2'].'",
        "country_dial_code":"+91",
        "mobile":"'.$row['3'].'",
        "source":"career_mantra",
        "state":"'.$row['12'].'",
        "Campus":"'.$row['6'].'",
        "city":"'.$row['8'].'",
        "Course":"'.$row['4'].'",
        "secret_key":"228a0331060cd6d994497bbed9801f88"}';
        echo $data_string;
        $curl = curl_init('https://api.nopaperforms.com/dataporting/19/career_mantra');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
        echo $json_response = curl_exec($curl);
         sleep(7);
         }/*---------------------------------------GNOIT start-----------------------------------------*/
         elseif($college_id == 'GNOIT'){
            $data_string ='{ 
        "college_id":"19",
        "name":"'.$row['1'].'",
        "email":"'.$row['2'].'",
        "country_dial_code":"+91",
        "mobile":"'.$row['3'].'",
        "source":"career_mantra",
        "state":"'.$row['12'].'",
        "Campus":"'.$row['6'].'",
        "city":"'.$row['8'].'",
        "Course":"'.$row['4'].'",
        "secret_key":"228a0331060cd6d994497bbed9801f88"}';
        echo $data_string;
        $curl = curl_init('https://api.nopaperforms.com/dataporting/19/career_mantra');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
        echo $json_response = curl_exec($curl);
         sleep(7);

         }/*--------------------------------------pbs start------------------------------------------*/
          elseif($college_id == 'pbs'){
            $data_string ='{ 
        "AuthToken": "PCET-19-08-2022",
        "Source": "pcet",
        "FirstName": "'.$row['1'].'",
        "Email": "'.$row['2'].'",
        "State": "'.$row['12'].'",
        "City": "'.$row['8'].'",
        "MobileNumber": "'.$row['3'].'",
        "leadName" : "Consultants",
        "LeadSource": "Mh. Alam_Career Mantra",
        "LeadCampaign": "Email",
        "LeadChannel": "Consultants",
        "Course": "'.$row['4'].'",
        "Center": "PBS",
        "Location": "PGDM",
        "Entity4": "'.$row['5'].'"
     }';
     echo $data_string;	
                $curl = curl_init('https://thirdpartyapi.extraaedge.com/api/SaveRequest/');
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
        echo $json_response = curl_exec($curl);
        sleep(7);

        }
        /*--------------------------------------dypusm start------------------------------------------*/
                        elseif ($college_id == 'dypusm') {
                    // Prepare data for API request
                    $data_string = '{
                        "AuthToken": "DYPUSM-08-02-2021",
                        "Source": "dypusm",
                        "FirstName": "'.$name.'",
                        "Email": "'.$email.'",
                        "MobileNumber": "'.$phone_no.'",
                        "State": "'.$state.'",
                        "City": "'.$city.'",
                        "Course": "'.$course.'",
                        "Center": "'.$specialization.'",
                        "leadName" : "Careermantra Leads",
                        "LeadSource": "30",
                        "LeadCampaign": "Email",
                        "LeadChannel": "2"
                       
                    }';

                    // Make API request
                    $curl = curl_init('https://thirdpartyapi.extraaedge.com/api/SaveRequest/');
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type: application/json",
                        "Content-Length: " . strlen($data_string)
                    ));

                    $json_response = curl_exec($curl);
                    curl_close($curl);

                    sleep(7); // Sleep for 7 seconds
                }
                 /*--------------------------------------Accurate start------------------------------------------*/
                                    elseif ($college_id == 'Accurate') {
                    // Prepare data for API request
                    $data = array(
                        "college_id" => "5752",
                        "source" => "career_mantra",
                        "name" => $name,
                        "email" => $email,
                        "mobile" => $mobile,
                        "state" => $state,
                        "city" => $city,
                        "course" => $course,
                        "secret_key" => "4787ca218349ed1ca1fe4ac7117fc5e3"
                    );

                    // Encode data to JSON format
                    $data_string = json_encode($data);

                    // Send data to API
                    $curl = curl_init('https://api.in8.nopaperforms.com/dataporting/5752/career_mantra');
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type: application/json",
                        "Content-Length: " . strlen($data_string)
                    ));

                    $json_response = curl_exec($curl);
                    curl_close($curl);

                    sleep(7); // Sleep for 7 seconds
                }

        /*--------------------------------------------JKBS start------------------------------------*/
        elseif($college_id == 'JKBS'){
            $data_string ='{
            "college_id":"139",
            "source":"career_mantra",
            "name":"'.$row['1'].'",
            "email":"'.$row['2'].'",
            "mobile":"'.$row['3'].'",
            "state":"'.$row['12'].'",
            "city":"'.$row['8'].'", 
            "course":"'.$row['4'].'",
            "secret_key":"db752e19721539fd65a92dfa0053498d"
		}';
        echo $data_string;
        $curl = curl_init('https://api.nopaperforms.com/dataporting/139/career_mantra');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
            echo  $json_response = curl_exec($curl);
            sleep(7);
        }
        /*--------------------------------------------PCU start------------------------------------*/
        elseif($college_id == 'PCU'){
            $data_string ='{
                "college_id":"5674",
                "source":"career_mantra",
                "name":"'.$row['1'].'",
                "email":"'.$row['2'].'",
                "mobile":"'.$row['3'].'",
                "state":"'.$row['12'].'",
                "city":"'.$row['8'].'", 
                "course":"'.$row['4'].'",
                "specialization":"'.$row['5'].'",
                "secret_key":"0ac841e88c5faf0f145087487174cc29"
		    }';
        echo $data_string;
         $curl = curl_init('https://api.in8.nopaperforms.com/dataporting/5674/career_mantra');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
            echo  $json_response = curl_exec($curl);
            sleep(7);
        }
        /*--------------------------------------------Amity start------------------------------------*/
        elseif($college_id == 'Amity'){
                $FirstName = $row['0'];
                $LastName = $row['1'];
              $data_string = '[
        {"Attribute":"FirstName","Value":"' .$FirstName. '"},
        {"Attribute":"LastName","Value":"' .$LastName. '"},
        {"Attribute":"EmailAddress","Value":"' .$row['2']. '"},
        {"Attribute":"Phone","Value":"' .$row['3']. '"},
        {"Attribute":"mx_Select_State","Value":"' .$row['12']. '"},
        {"Attribute":"mx_Select_City","Value":"' .$row['8']. '"},
        {"Attribute":"mx_College_Name","Value":""},
        {"Attribute":"mx_Campus","Value":"' .$row['6']. '"},
        {"Attribute":"Course","Value":"' .$row['4']. '"},
        {"Attribute":"Source","Value":"Career Mantra"},
        {"Attribute":"ProspectID","Value":""},
        {"Attribute":"SearchBy","Value":"Phone"}]';
        echo $data_string;
        try
            {
        $curl = curl_init('https://api-in21.leadsquared.com/v2/LeadManagement.svc/Lead.Capture?accessKey=u$r395542de55f03410c31f73272d5ae72b&secretKey=db68fb3a815f10550137b41d1846605684e7843c');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
            echo $json_response = curl_exec($curl);
             sleep(7);
                curl_close($curl);
            }
            catch(Exception $ex)
            {
                curl_close($curl);
            }
        }
        /*---------------------------------------------------------------------*/
        /*--------------------------------------------Graphic Era start------------------------------------*/
        elseif($college_id == 'Graphic Era'){
            $data_string ='{
                "college_id":"6861",
                "source":"career_mantra",
                "name":"'.$row['1'].'",
                "email":"'.$row['2'].'",
                "mobile":"'.$row['3'].'",
                "campus":"'.$row['6'].'",
                "course":"'.$row['4'].'",
                "specialization":"'.$row['5'].'",
                "state":"'.$row['12'].'",
                "city":"'.$row['8'].'",
                "medium":"'.$row['10'].'",
                "secret_key":"45c20f12941b42a1a662b7f1613e8db6"
            }';
            
            echo $data_string;
            
            $curl = curl_init('https://api.in4.nopaperforms.com/dataporting/6861/career_mantra');
            
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                "Content-Type:application/json",
                "Content-Length:" . strlen($data_string)
            ));
            
            echo $json_response = curl_exec($curl);
            sleep(7);
        }
        /*--------------------------------------------Graphic Era end------------------------------------*/
        
/*--------------------------------------------Dev Bhoomi start------------------------------------*/
elseif ($college_id == 'DBUU') {
       $data = array(
        array("Attribute" => "FirstName", "Value" => $name), 
        array("Attribute" => "LastName", "Value" => ""), 
        array("Attribute" => "EmailAddress", "Value" => $email),
        array("Attribute" => "Phone", "Value" => $phone_no),
        array("Attribute" => "mx_Select_State", "Value" => $state),
        array("Attribute" => "mx_Select_City", "Value" => $city),
        array("Attribute" => "mx_Program_Level", "Value" => $college_campus),
        array("Attribute" => "mx_Course_Level", "Value" => $course),
        array("Attribute" => "mx_courses", "Value" => $specialization),
        array("Attribute" => "Source", "Value" => "Career Mantra"),
        array("Attribute" => "ProspectID", "Value" => ""),
        array("Attribute" => "SearchBy", "Value" => "Phone")
    );
  
       

    $data_string = json_encode($data);

    try {
        $curl = curl_init('https://publisher-api.customui.leadsquared.com/api/leadCapture/NDAzNDc=/?token=e11c4911-c9f4-434c-8b93-af8c34936250');

       curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Content-Type:application/json",
            "Content-Length:" . strlen($data_string)
        ));
        $json_response = curl_exec($curl);

        echo $json_response = curl_exec($curl);
             sleep(7);
                curl_close($curl);
            }
            catch(Exception $ex)
            {
                curl_close($curl);
            }
        }
 /*---------------------------------------------------------------------*/


        elseif($college_id == 'Sunstone'){
            '{
                "Authorization": "cac0713d-076b-4817-b713-af8bc49e5a66"
            }';
            $data_string ='{ 
        "AuthToken": "cac0713d-076b-4817-b713-af8bc49e5a66",
        "Source":"Aff_4074CARE", 
        "name": "'.$row['1'].'",
        "email": "'.$row['2'].'",
        "State": "'.$row['12'].'",
        "mobile": "'.$row['3'].'",
        "leadstatus" : "",
        "lead_score": "",
        "response_type": "",
        "utm_source": "aff_4074CARE",
        "utm_campaign": "",
        "utm_medium": "",
        "campus_name": "'.$row['6'].'",
        "program": "'.$row['4'].'"
     }';
        echo $data_string;
        $curl = curl_init('https://student-api.sunstone.in/lead/leadPush');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
         echo $json_response = curl_exec($curl);
         sleep(7);

        }/*---------------------------------------ShardaUniversity-------------------------------------------*/
        elseif($college_id=='ShardaUniversity'){
          //  echo"testing ok";
            $data_string='{
        "token": "7726138ab5f9a5ebc51b6838abdc5acf0f8760714c9d2978ecf771ae776c6104",
            "name": "'.$row['1'].'",
            "email":"'.$row['2'].'",
            "mobile":"'.$row['3'].'",
            "state_code":"'.$row['12'].'",
            "program_name":"'.$row['4'].'",
            "plan_name":""
         }';
        echo $data_string;
        $curl = curl_init('https://aweblms.sharda.ac.in/api/vendorapi/addLead');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
           echo  $json_response = curl_exec($curl);
            sleep(7);

        }/*----------------------------------------------------------------------------------------------------*/
        
        elseif($college_id=='GNGroup')
        {
            $data_string ='{
            "college_id":"503",
            "name":"'.$row['1'].'",
            "email":"'.$row['2'].'",
            "mobile":"'.$row['3'].'",
            "source":"career_mantra",
            "state":"'.$row['12'].'",
            "city":"'.$row['8'].'",
            "course":"'.$row['4'].'",
            "secret_key":"25f0b2522349f09168911d5cb42ac201"
		}';
        
        echo $data_string;
        
         $curl = curl_init('https://api.nopaperforms.com/dataporting/503/career_mantra');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
            echo  $json_response = curl_exec($curl);
            sleep(7);
         }/*----------------------------------------------------------------------------------------------------*/
         
         elseif($college_id=='Podhar')
        {
            $data_string ='{
            "college_id":"5610",
            "source":"collegedunia",
            "name":"'.$row['1'].'",
            "email":"'.$row['2'].'",
            "mobile":"'.$row['3'].'",
            "state":"'.$row['12'].'",
            "city":"'.$row['8'].'", 
            "course":"'.$row['4'].'",
            "secret_key":"63fada9cf0a8880941926adc291c196d"
		}';
        echo $data_string;
        $curl = curl_init('https://api.in5.nopaperforms.com/dataporting/5610/collegedunia');
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
        echo  $json_response = curl_exec($curl);
        sleep(7);
         }/*----------------------------------------------------------------------------------------------------*/
              
         elseif($college_id=='Chanakya')
        {
            $data_string ='{
            "college_id":"5604",
            "name":"'.$row['1'].'",
            "email":"'.$row['2'].'",
            "mobile":"'.$row['3'].'",
            "source":"career_mantra",
            "state":"'.$row['12'].'",
            "city":"'.$row['8'].'",
            "campus":"'.$row['6'].'", 
            "course":"'.$row['4'].'",
            "specialization":"'.$row['5'].'",
            "secret_key":"f863b4bb0254b31ceb6d229743304dab"
		}';
        
        echo $data_string;
        
         $curl = curl_init('https://api.in8.nopaperforms.com/dataporting/5604/career_mantra');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
            echo  $json_response = curl_exec($curl);
            sleep(7);
         }/*----------------------------------------------------------------------------------------------------*/
              elseif($college_id=='Mody University')
        {
            $data_string ='{
            "source":"career_mantra",
            "name":"'.$row['1'].'",
            "email":"'.$row['2'].'",
            "number":"'.$row['3'].'",
            "state":"'.$row['12'].'",
            "city":"'.$row['8'].'", 
            "course":"'.$row['4'].'",
            "gender":"'.$row['10'].'",
		}';
        echo $data_string;
        $curl = curl_init('https://applications.modyuniversity.ac.in/?utm_source=career+mantra&utm_medium=Alam+Hussain&utm_campaign=consultant');
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
        echo  $json_response = curl_exec($curl);
        sleep(7);
         }
         /*----------------------------------------------------------------------------------------------------*/
            elseif ($college_id == 'IZee') {
    $data = array(
        "AuthToken" => "IZEEBSCHOOL-17-11-2023",
        "Source" => "izeebschool",
        "FirstName" => $name,
        "Email" => $email,
        "State" => $state,
        "City" => $city,
        "MobileNumber" => $phone_no,
        "leadName" => "Consultants",
        "LeadSource" => "Mh. Alam_Career Mantra",
        "LeadCampaign" => "Email",
        "LeadChannel" => "Consultants",
        "Course" => $course
    );

    $data_string = json_encode($data);
    echo $data_string;

    $curl = curl_init('https://thirdpartyapi.extraaedge.com/api/SaveRequest/');
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        "Content-Type: application/json",
        "Content-Length: " . strlen($data_string)
    ));

    echo $json_response = curl_exec($curl);
    sleep(7);
}

         /*----------------------------------------------------------------------------------------------------*/
              elseif($college_id=='Banglore school of design')
        {
            $data_string ='{
            "college_id":"5216",
            "source":"career_mantra",
            "name":"'.$row['1'].'",
            "email":"'.$row['2'].'",
            "mobile":"'.$row['3'].'",
            "state":"'.$row['12'].'",
            "city":"'.$row['8'].'", 
            "course":"'.$row['4'].'",
            "secret_key":"1b4f55227237f73a293413ba0d67fd96"
		}';
        echo $data_string;
        $curl = curl_init('https://api.in5.nopaperforms.com/dataporting/5216/career_mantra');
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
        echo  $json_response = curl_exec($curl);
        sleep(7);
         }
   /*----------------------------------------------------------------------------------------------------*/
              elseif ($college_id == 'IIBS') {
                                    $data = array(
                                        "college_id" => "392",
                                        "source" => "career_mantra",
                                        "name" => $name,
                                        "email" => $email,
                                        "mobile" => $phone_no,
                                        "campus" => $college_campus,
                                        "state" => $state,
                                        "city" => $city,
                                        "course" => $course,
                                        "secret_key" => "d7f3e1cb4f9fd290b60646c5125d2387"
                                    );

                                  echo  $data_string = json_encode($data);

                                    // Initialize cURL session
                                    $curl = curl_init('https://api.nopaperforms.com/dataporting/392/career_mantra');
                                    // Set cURL options
                                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                                    curl_setopt($curl, CURLOPT_HEADER, 0);
                                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                                        "Content-Type: application/json",
                                        "Content-Length: " . strlen($data_string)
                                    ));

                                    // Execute cURL request
                                    $json_response = curl_exec($curl);
                                    curl_close($curl);
                                    echo $json_response;
                                    sleep(7);
                                }
   /*----------------------------------------------------------------------------------------------------*/
                 elseif ($college_id == 'UBS') {
                                        // Prepare data payload
                                        $data = array(
                                            "college_id" => "382",
                                            "source" => "career_mantra",
                                            "name" => $name,
                                            "email" => $email,
                                            "mobile" => $phone_no, 
                                            "state" => $state,
                                            "city" => $city,
                                            "course" => $course,
                                            "specialization" => $specialization,
                                            "secret_key" => "5e6d77be0eb15a7767819acdb7f4d8ee"
                                        );
                                     echo   $data_string = json_encode($data);
                                        $curl = curl_init('https://api.nopaperforms.com/dataporting/382/career_mantra');

                                        // Set cURL options
                                        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                                        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                                        curl_setopt($curl, CURLOPT_HEADER, 0);
                                        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                                        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                                        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                                            "Content-Type: application/json",
                                            "Content-Length: " . strlen($data_string)
                                        ));

                                    // Execute cURL request
                                    $json_response = curl_exec($curl);
                                    curl_close($curl);
                                    echo $json_response;
                                    sleep(7);
                                }
   /*----------------------------------------------------------------------------------------------------*/
              elseif($college_id=='IMS-Noida')
        {
              $data_string = '[
        {"Attribute":"FirstName","Value":"' .$row['0']. '"},
        {"Attribute":"LastName","Value":"' .$row['1']. '"},
        {"Attribute":"EmailAddress","Value":"' .$row['2']. '"},
        {"Attribute":"Phone","Value":"' .$row['3']. '"},
        {"Attribute":"mx_State","Value":"' .$row['12']. '"},
        {"Attribute":"mx_City","Value":"' .$row['8']. '"},
        {"Attribute":"mx_Course_Interested","Value":"' .$row['4']. '"},
        {"Attribute":"Source","Value":"Career Mantra"},
        {"Attribute":"ProspectID","Value":""},
        {"Attribute":"SearchBy","Value":"Phone"}]';
        echo $data_string;
        try
            {
        $curl = curl_init('https://api-in21.leadsquared.com/v2/LeadManagement.svc/Lead.Capture?accessKey=u$r100637f9f2e8ea2b09a3b3592fde0ff6&secretKey=961a3e83f01440c872a430b8240753e3be88413b');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
            echo $json_response = curl_exec($curl);
             sleep(7);
                curl_close($curl);
            }
            catch(Exception $ex)
            {
                curl_close($curl);
            }
        }
              elseif($college_id=='IMS-Noida-Law')
        {
              $data_string = '[
        {"Attribute":"FirstName","Value":"' .$row['0']. '"},
        {"Attribute":"LastName","Value":"' .$row['1']. '"},
        {"Attribute":"EmailAddress","Value":"' .$row['2']. '"},
        {"Attribute":"Phone","Value":"' .$row['3']. '"},
        {"Attribute":"mx_State","Value":"' .$row['12']. '"},
        {"Attribute":"mx_City","Value":"' .$row['8']. '"},
        {"Attribute":"mx_Course_Interested","Value":"' .$row['4']. '"},
        {"Attribute":"Source","Value":"Career Mantra"},
        {"Attribute":"ProspectID","Value":""},
        {"Attribute":"SearchBy","Value":"Phone"}]';
        echo $data_string;
        try
            {
        $curl = curl_init('https://api-in21.leadsquared.com/v2/LeadManagement.svc/Lead.Capture?accessKey=u$r5d9432b0de00a751830a86ef09b7f2ba&secretKey=7bf5af1ce7f2fa4905083deecbf03a0db6404b85');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
            echo $json_response = curl_exec($curl);
             sleep(7);
                curl_close($curl);
            }
            catch(Exception $ex)
            {
                curl_close($curl);
            }
        }
              elseif($college_id=='CSMU (Chatrapati shivaji)')
        {
            $data_string ='{
            "college_id":"5013",
            "source":"career_mantra",
            "name":"'.$row['1'].'",
            "email":"'.$row['2'].'",
            "mobile":"'.$row['3'].'",
            "state":"'.$row['12'].'",
            "city":"'.$row['8'].'", 
            "course":"'.$row['4'].'",
            "specialization":"'.$row['5'].'",
            "secret_key":"50ee7bc0a9aca6b409333a9586263f0e"
		}';
        echo $data_string;
        $curl = curl_init('https://api.nopaperforms.com/dataporting/5013/career_mantra');
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
        echo  $json_response = curl_exec($curl);
        sleep(7);
         }
              elseif($college_id=='Harsha Institutions')
        {
            $data_string ='{
            "college_id":"5750",
            "source":"career_mantra",
            "name":"'.$row['1'].'",
            "email":"'.$row['2'].'",
            "mobile":"'.$row['3'].'",
            "state":"'.$row['12'].'",
            "city":"'.$row['8'].'", 
            "campus":"'.$row['6'].'",
            "course":"'.$row['4'].'",
            "specialization":"'.$row['5'].'",
            "secret_key":"0b4e575e35d300dee2aec760f554ac4f"
		}';
        echo $data_string;
        $curl = curl_init('https://api.in8.nopaperforms.com/dataporting/5750/career_mantra');
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
        echo  $json_response = curl_exec($curl);
        sleep(7);
         }
     /*--------------------------------------Transstandia start---------------------*/         
         elseif($college_id=='Transstandia')
        {
            $data_string ='{
            "college_id":"5060",
            "source":"career_mantra",
            "name":"'.$row['1'].'",
            "email":"'.$row['2'].'",
            "mobile":"'.$row['3'].'",
            "state":"'.$row['12'].'",
            "city":"'.$row['8'].'", 
            "course":"'.$row['4'].'",
            "secret_key":"3a0e506ed9a4d216ec1dd1090e70e98e"
		}';
        echo $data_string;
        $curl = curl_init('https://api.in5.nopaperforms.com/dataporting/5060/career_mantra');
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
        echo  $json_response = curl_exec($curl);
        sleep(7);
         }
/*--------------------------------------Transstandia end---------------------*/
              /*--------------------------------------St. Andrews start---------------------*/         
         elseif($college_id=='St. Andrews')
        {
            $FirstName = $row['0'];
                $LastName = $row['1'];
              $data_string = '[
        {"Attribute":"FirstName","Value":"' .$FirstName. '"},
        {"Attribute":"LastName","Value":"' .$LastName. '"},
        {"Attribute":"EmailAddress","Value":"' .$row['2']. '"},
        {"Attribute":"Phone","Value":"' .$row['3']. '"},
        {"Attribute":"mx_course","Value":"' .$row['4']. '"},
        {"Attribute":"Source","Value":"careermantra"},
        {"Attribute":"ProspectID","Value":""},
        {"Attribute":"SearchBy","Value":"Phone"}]';
        echo $data_string;
        try
            {
        $curl = curl_init('https://publisher-api.customui.leadsquared.com/api/leadCapture/NTcwMjE=/?token=e11c4911-c9f4-434c-8b93-af8c34936250');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
            echo $json_response = curl_exec($curl);
             sleep(7);
                curl_close($curl);
            }
            catch(Exception $ex)
            {
                curl_close($curl);
            }
         }
/*--------------------------------------St. Andrews end---------------------*/
              /*--------------------------------------Adypu start------------------------------------------*/
          elseif($college_id == 'Adypu'){
            $data_string ='{ 
        "AuthToken": "ADYPU-16-12-2021",
        "Source": "adypu",
        "FirstName": "'.$row['1'].'",
        "Email": "'.$row['2'].'",
        "State": "'.$row['12'].'",
        "City": "'.$row['8'].'",
        "MobileNumber": "'.$row['3'].'",
        "LeadSource": "307",
        "LeadChannel": "2",
        "Course": "'.$row['6'].'",
        "Center": "'.$row['4'].'",
        "Location": "'.$row['5'].'"
     }';
     echo $data_string;	
                $curl = curl_init('https://thirdpartyapi.extraaedge.com/api/SaveRequest/');
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
        echo $json_response = curl_exec($curl);
        sleep(7);
        }
      /*--------------------------------------Adypu end---------------------*/
     /*-------------------------------------IFIM College start---------------------------------------*/                
    elseif($college_id == 'IFIM College'){
                $data_string ='{
                "college_id":"371",
                "name":"'.$row['1'].'",
                "email":"'.$row['2'].'",
                "mobile":"'.$row['3'].'",
                "course":"'.$row['4'].'",
                "source":"career_mantra",
                "state":"'.$row['12'].'",
                "city":"'.$row['8'].'",
                "specialization":"'.$row['5'].'",
                "secret_key":"bb4f3fef494a9a7aba57f9e99a3d92c7"
    		}';
    	echo $data_string;	
        $curl = curl_init('https://api.nopaperforms.com/dataporting/371/career_mantra');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
             echo   $json_response = curl_exec($curl);
                 sleep(7);
            } 
     /*-------------------------------------IFIM College End---------------------------------------*/
     /*-------------------------------------IFIM Law School start---------------------------------------*/                
    elseif($college_id == 'IFIM Law School'){
                $data_string ='{
                "college_id":"370",
                "name":"'.$row['1'].'",
                "email":"'.$row['2'].'",
                "mobile":"'.$row['3'].'",
                "course":"'.$row['4'].'",
                "source":"career_mantra",
                "state":"'.$row['12'].'",
                "city":"'.$row['8'].'",
                "specialization":"'.$row['5'].'",
                "secret_key":"166c4ef234ca43b487927a6f8dcc67ec"
    		}';
    	echo $data_string;	
        $curl = curl_init('https://api.nopaperforms.com/dataporting/370/career_mantra');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
             echo   $json_response = curl_exec($curl);
                 sleep(7);
            } 
     /*-------------------------------------IFIM Law School End---------------------------------------*/
     /*-------------------------------------Marwadi University start---------------------------------------*/                
    elseif($college_id == 'Marwadi University'){
                $data_string ='{
                "college_id":"456",
                "name":"'.$row['1'].'",
                "email":"'.$row['2'].'",
                "mobile":"'.$row['3'].'",
                "source":"86",
                "state":"'.$row['12'].'",
                "city":"'.$row['8'].'",
                "campus":"'.$row['6'].'",
                "course":"'.$row['4'].'",
                "specialization":"'.$row['5'].'",
                "secret_key":"/p93AZLnKibc/wJs1JHXyA=="
    		}';
    	echo $data_string;	
        $curl = curl_init('https://crm.marwadiuniversity.ac.in:553/APIIntegration.aspx');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
             echo   $json_response = curl_exec($curl);
                 sleep(7);
            } 
     /*-------------------------------------Marwadi University End---------------------------------------*/ 
     /*--------------------------------------------St.Wilfred start------------------------------------*/
        elseif($college_id == 'St.Wilfred'){
                $FirstName = $row['0'];
                $LastName = $row['1'];
              $data_string = '[
        {"Attribute":"FirstName","Value":"' .$FirstName. '"},
        {"Attribute":"LastName","Value":"' .$LastName. '"},
        {"Attribute":"EmailAddress","Value":"' .$row['2']. '"},
        {"Attribute":"Phone","Value":"' .$row['3']. '"},
        {"Attribute":"mx_State","Value":"' .$row['12']. '"},
        {"Attribute":"mx_City","Value":"' .$row['8']. '"},
        {"Attribute":"mx_Institute","Value":"' .$row['6']. '"},
        {"Attribute":"mx_Applied_for_Course","Value":"' .$row['4']. '"},
        {"Attribute":"Source","Value":"Career Mantra"},
        {"Attribute":"ProspectID","Value":""},
        {"Attribute":"SearchBy","Value":"Phone"}]';
        echo $data_string;
        try
            {
        $curl = curl_init('https://publisher-api.customui.leadsquared.com/api/leadCapture/Njg0ODE=/?token=e11c4911-c9f4-434c-8b93-af8c34936250');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
            echo $json_response = curl_exec($curl);
             sleep(7);
                curl_close($curl);
            }
            catch(Exception $ex)
            {
                curl_close($curl);
            }
        }
        /*-------------------------------------St.Wilfred End---------------------------------------*/ 

/*---------------------------------GIBS start-----------------------------------------------*/
         elseif($college_id == 'GIBS'){
            $data_string ='{ 
        "college_id":"374",
        "name":"'.$row['1'].'",
        "email":"'.$row['2'].'",
        "country_dial_code":"+91",
        "mobile":"'.$row['3'].'",
        "source":"career_mantra",
        "state":"'.$row['12'].'",
        "city":"'.$row['8'].'",
        "course":"'.$row['4'].'",
        "secret_key":"3eff570317f445677f1c72a4f2c892f2"}';
        echo $data_string;
        $curl = curl_init('https://api.nopaperforms.com/dataporting/374/career_mantra');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
        echo $json_response = curl_exec($curl);
         sleep(7);
}

        /*--------------------------------------Transstandia Ahmdabad start---------------------*/         
         elseif($college_id=='Transstandia Ahmdabad')
        {
            $data_string ='{
            "college_id":"4086",
            "source":"career_mantra",
            "name":"'.$row['1'].'",
            "email":"'.$row['2'].'",
            "mobile":"'.$row['3'].'",
            "state":"'.$row['12'].'",
            "city":"'.$row['8'].'", 
            "course":"'.$row['4'].'",
            "secret_key":"4a50d46dfc61b9a68eb837d7ccf0719b"
		}';
        echo $data_string;
        $curl = curl_init('https://api.nopaperforms.com/dataporting/4086/career_mantra');
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
        echo  $json_response = curl_exec($curl);
        sleep(7);
         }
/*--------------------------------------Transstandia Ahmdabad end---------------------*/ 
        /*--------------------------------------Seekho start---------------------*/         
         elseif($college_id=='Seekho')
        {
            $data_string ='{
            "college_id":"5757",
            "source":"career_mantra",
            "name":"'.$row['1'].'",
            "email":"'.$row['2'].'",
            "mobile":"'.$row['3'].'",
            "state":"'.$row['12'].'",
            "city":"'.$row['8'].'", 
            "campus":"'.$row['4'].'", 
            "course":"'.$row['5'].'",
            "secret_key":"70dd3ff0529f4186973279e72cfb43c6"
		}';
        echo $data_string;
        $curl = curl_init('https://api.in8.nopaperforms.com/dataporting/5757/career_mantra');
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
        echo  $json_response = curl_exec($curl);
        sleep(7);
         }

//----------------------------meritto---------------

elseif($college_id=='Lexicon')
        {
            $data_string ='{
            "college_id":"375",
            "source":"career_mantra",
            "name":"'.$row['1'].'",
            "email":"'.$row['2'].'",
            "mobile":"'.$row['3'].'",
            "state":"'.$row['12'].'",
            "city":"'.$row['8'].'", 
            "campus":"'.$row['4'].'", 
            "course":"'.$row['5'].'",
            "secret_key":"e1c2e501cb7b5d4ba5f0eef5a7d350d0"
		}';
        echo $data_string;
        $curl = curl_init('https://api.nopaperforms.com/dataporting/375/career_mantra');
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
        echo  $json_response = curl_exec($curl);
        sleep(7);
         }

         //----------------------------Alliance---------------

elseif($college_id=='Alliance')
        {
            $data_string ='{
            "college_id":"207",
            "source":"career_mantra",
            "name":"'.$row['1'].'",
            "email":"'.$row['2'].'",
            "mobile":"'.$row['3'].'",
            "state":"'.$row['12'].'",
            "city":"'.$row['8'].'", 
            "campus":"'.$row['4'].'", 
            "course":"'.$row['5'].'",
            "secret_key":"7cd1b1ae7671b9a7ab78e8ece637bf33"
		}';
        echo $data_string;
        $curl = curl_init('https://api.nopaperforms.com/dataporting/375/career_mantra');
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
        echo  $json_response = curl_exec($curl);
        sleep(7);
         }


         }/*--------------------------------------Aditya start------------------------------------------*/
          elseif($college_id == 'Aditya'){
              $data_string ='{
    "AuthToken": "ADITYA-28-03-2022",
    "Source": "aditya",
    "FirstName": "'.$_POST['FirstName'].'",
    "MobileNumber": "'.$_POST['MobileNumber'].'",
    "Email": "'.$_POST['Email'].'",
    "State": "Maharashtra",
    "City": "Pune",
    "LeadSource": "1539",
    "leadType": "Online",
    "Course": "2",
    "Center": "2",
    "Location": "1"
		}';
        
        echo $data_string;
        
         $curl = curl_init('https://thirdpartyapi.extraaedge.com/api/SaveRequest/');
 
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        "Content-Type:application/json",
                        "Content-Length:" . strlen($data_string)
                    ));
  
echo  $json_response = curl_exec($curl);
 sleep(7);

/*--------------------------------------Seekho end---------------------*/
 }/*------if coundition close--*/
 else
    {
        $count = "1";
    }
          
 }/*----------------foreach loop close-----------------------*/
    
      /*  if(isset($msg))
        {
            $_SESSION['message'] = "Successfully Imported";
            header('Location: index.php');
            exit(0);
        }
        else
        {
            $_SESSION['message'] = "Not Imported";
            header('Location: index.php');
            exit(0);
        }*/
      
    }/*------------------------if files in array close-------------------------------------------*/

    else
    {
        $_SESSION['message'] = "Invalid File";
        header('Location: index.php');
        exit(0);
    }
}/*------------------------if files $_POST check-------------------------------------------*/
