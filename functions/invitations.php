


<?Php








/*

//AWRARDS LOOP


foreach($ros as $i =>$item){ // outer loop.
    extract($item);
    if(!in_array("nomination",explode(" ",$classes[0]))){
       // continue;
      }
    print "<h3>".showCounter($counter)." | $title</h3>";
   // print "<strong>$slug</strong>";
    $current_award = $slug;
    $awards_list[$current_award] = $item['title'];
/*
print "<ol>";
    foreach($nominees as $c => $nominee){
       $current_nomination = $nominee['slug'];
      
       $id = $nominee['post']->ID;
       $type=$nominee['post']->post_type;
      


       if($type == 'profile'|| $type == 'resource'){
    }
        if(!in_array($current_nomination,$nominations)){
            $nominations[$current_nomination] = ["nominations"=>[],"nominee"=>[
                "name"=>$nominee['title'],
                "email"=>@$nominee['meta']['email'][0],
                "website"=>@$nominee['meta']['website'][0],
                "resource_url"=>@$nominee['meta']['resource_url'][0],
                "_thumbnail_id"=>@$nominee['meta']['_thumbnail_id'][0],
                
                
            ]];
            
         }

        array_push($nominations[$current_nomination]['nominations'],$current_award . " ". $current_nomination);

      

      
       print "<li><a href='/wp-admin/post.php?post=$id&action=edit' target='_blank'>$nominee[title]</a>";
    //    print " | ". $type. " | ";

        getMetaLink($nominee['meta'],'email');

        getMetaLink($nominee['meta'],'twitter');
        getMetaLink($nominee['meta'],'website');
        getMetaLink($nominee['meta'],'resource_url');
        
        print "</li>";


       $nominations = getNominee(@$nominee,$nominations,$current_award,$current_nomination);

 
   
    }
print "</ol>";



$counter++;


}


aggregateNominations($nominations);

function aggregateNominations($nominations){


    foreach($nominations as $n => $nominee){
        print $name= $nominee['nominee']['name'];
     print $email= $nominee['nominee']['email'];
        print"<BR>";
        $subject = "Poly Award Nomination";
        if(count($nominee['nominations'])>1){
            $subject = "Poly Award Nominations";
        }
        $body="test";
    
        foreach($nominee['nominations'] as $nom => $award){
         //  print $award;
            print @$awards_list[$award];
            
            print"<BR>";
    
    
        }
    
    
    
    
        if($email != ''){
        print "SUBJECT= $subject<BR>";
        print "<a href='mailto:$email?subject=$subject&body=$body'>EMAIL</a>";
            // print @var_dump($nominee);
        }
    
        print "<BR><BR>";
    }

}









*/

 // located in functions-navigation.php






$invitation_statuses = [
    "team"=>[],
    "no-status"=>[],
    "needs-invite"=>[],
    "invited"=>[],
    "agreed"=>[],
    "registered-no-release"=>[],
    "registered"=>[],
    "calendar-sent"=>[],
    "calendar-sent-no-release"=>[],
    "calendar-sent-no-registration"=>[],
    
    "confirmed-no-registration"=>[],
    "confirmed-no-release"=>[],


    "prerecord"=>[],
    
    "complete"=>[]

];



if(@$_GET['calendar']){
    print "<a href='?'>Invite mode</a>";
} 
 else if (@$_GET['follow-up']) {
    print "<a href='?follow-up=1'>Followup mode</a>";
} else {
    print "<a href='?calendar=1'>Calendar mode</a>";
}
print " | <a href='?sheet=1'>Sheet mode</a>";
print "<hr><BR>";



?>





<?php




$offset = -4;
if(@$_GET['offset']){
    $offset = $_GET['offset'];
}




//EVENT LEVEL
foreach($ros as $i =>$item){ // this is the top level of the event itself
    //AWARD
   if($i==0){
       $code = "A";
    } else if($i==1){
        $code = "C";
    }
    $event_title = $item['post']->post_title;
   $link = get_permalink($item['post']->post_title);
    $session_type = @$item['event_type'][0];
   $green_room_url = @$item['meta']['green_room_url'][0];
   $release_form_url = @$item['meta']['release_form_url'][0];
 



   $start = intval(@$item['meta']['utc_start'][0]);
  // $start = $start-(($offset*3600)*-1);// corrects timezone to minue hours
       
   
   
    $sessions = $item['children'];
    $speaker_status = [];
    $holds = [];
    $session_counter = 0;



// HEADING OR EACH OUTER LOOP
    if(@$_GET['sheet']){
        // Only print header once for the first event
        if($i == array_key_first($ros)){
            print "<pre>";
            print "CODE\tSTART\tEND\tDURATION\tSEGMENT\tTYPE\tTALENT\n";
        }
    } else {
        print "<h1><hr>$event_title</h1>";
        print "START TIME: " .date("Y-M-D H:i",$start)."<BR>";
    }

    if(@$_GET['table']){
        print "<table border='1' cellpadding='5' cellspacing='0'>";
        print "<tr><th>CODE</th><th>TIME</th><th>DURATION (min)</th><th>SESSION / PERSON</th></tr>";
    }

    //SESSION LEVEL
   foreach($sessions as $s => $session){ // THIS IS THE EVENT SESSION LOOP


 $display_session_counter = $session_counter;
    if($session_counter<10){{
        $display_session_counter = "0".$session_counter;
    }}
    $duration = @$session['meta']['duration'][0]." Minutes";
    $duration = @$session['meta']['event_length_seconds'][0]." seconds";
    


//    var_dump($session);
    $session_type = @$session['event_type'];
    if($session_type ==  ''){
        PRINT $session_type = "<STRONG>SESSION TYPE NOT SET</STRONG>";                
      }

  // var_dump($session); 
        $session_script = '';
       
       
        $speaker_status[$session['post']->ID] = [
            "session_time"=> date("H:i",$start),
                
            "session"=> @$session['post']->post_title,
            "speakers"=> [],
                
        ];
       
        

        

        $session_id = $session['post']->ID;
        $session_slug=sanitize_title($session['post']->post_title);
        $session_blurb= do_blocks($session['post']->post_content);
        $green_room_time = $start - (15*60);
        if(@$_GET['invites']){
        print "<h3>".$session['post']->post_title;
        print "</h3>";
        
        print "GREEN ROOM TIME: ".date("H:i",$green_room_time)."<BR>";
        print "START TIME: " .date("H:i",$start);
        print "$duration<BR>";
    }
 $duration = $session['event_length_seconds'];
 
 if(!intval($duration)){
    $duration = 0;
 }
$minutes = floor(intval($duration) / 60); // Get whole minutes
$seconds = $duration % 60; // Get remaining seconds

$session_type = ucwords(str_replace('-', ' ', strtolower($session_type)));
//print "<span class='session-name'>".$session['post']->post_title."</span>"."<BR>";

if($duration>0){
    if($duration != ''){
       @$end+=intval(trim($duration));
      }
      $end = $start + ($duration);
}
if(@$_GET['sheet']){
    $session_classes = @$session['classes'];
    $is_award = (in_array(@$session_classes[0], ['nomination', 'honor']));

    if($is_award){
        // Count nominees (Level 3 children without "presenter" class)
        $presenter_name = '';
        $nominee_count = 0;
        $talent_names = [];

        if(!empty($session['children'])){
            foreach($session['children'] as $ch){
                if(@$ch['classes'][0] == 'presenter'){
                    $presenter_name = $ch['title'];
                } else {
                    $nominee_count++;
                    // Collect Level 4 names
                    if(!empty($ch['children'])){
                        foreach($ch['children'] as $l4){
                            $talent_names[] = $l4['title'];
                        }
                    }
                }
            }
        }

        $talent_str = implode(', ', $talent_names);
        $category_name = $session['title'];

        // 5 sub-rows
        $sub_rows = [
            ['Host Intro', 60, $category_name, ''],
            ['Presenter Intro', 60, $category_name, $presenter_name],
            ['Nomination Reel', 30 * max($nominee_count, 1), $category_name, $talent_str],
            ['Reveal', 30, $category_name, ''],
            ['Acceptance Speech', 60, $category_name, $talent_str],
        ];

        foreach($sub_rows as $sub){
            $sub_end = $start + $sub[1];
            $dur_min = $sub[1] / 60;
            $dur_display = (floor($dur_min) == $dur_min)
                ? number_format($dur_min, 0)
                : number_format($dur_min, 1);

            $start_display = date("g:i A", $start + ($offset * 3600));
            $end_display = date("g:i A", $sub_end + ($offset * 3600));

            print "$code$display_session_counter\t";
            print "$start_display\t";
            print "$end_display\t";
            print "$dur_display\t";
            print "$sub[0]\t";
            print "$sub[2]\t";
            print "$sub[3]\n";

            $start = $sub_end;
        }

        $end = $start;

    } else {
        // Regular session — one row
        $dur_sec = intval(@$session['event_length_seconds']);
        $end = $start + $dur_sec;
        $dur_min = $dur_sec / 60;
        $dur_display = ($dur_sec == 0) ? '0'
            : ((floor($dur_min) == $dur_min)
                ? number_format($dur_min, 0)
                : number_format($dur_min, 1));

        $start_display = date("g:i A", $start + ($offset * 3600));
        $end_display = date("g:i A", $end + ($offset * 3600));
        $type_display = ucwords(str_replace('-', ' ',
            strtolower(@$session['event_type'])));

        // Collect talent (Level 3 profile children)
        $talent = [];
        if(!empty($session['children'])){
            foreach($session['children'] as $ch){
                if(@$ch['post']->post_type == 'profile'){
                    $talent[] = $ch['title'];
                }
            }
        }
        $talent_str = implode(', ', $talent);

        print "$code$display_session_counter\t";
        print "$start_display\t";
        print "$end_display\t";
        print "$dur_display\t";
        print "$session[title]\t";
        print "$type_display\t";
        print "$talent_str\n";
    }

} else if(@$_GET['table']){
    // Table view: CODE, Time, Duration (minutes), Session Title, then list people
    print "<tr>";
    print "<td>$code"."$display_session_counter</td>";
    print "<td>".date("h:ia",$start)."</td>";
    print "<td>$minutes</td>";
    print "<td>$session[title]</td>";
    print "</tr>";
    
    // List people for this session
    if(array_key_exists("children",$session)){
        foreach($session['children'] as $p => $speaker){
            if(@$speaker['post']->post_type == 'profile'){
                print "<tr>";
                print "<td colspan='3'></td>";
                print "<td>".$speaker['post']->post_title."</td>";
                print "</tr>";
            }
        }
    }

} else if(@$_GET['list']){
    print "$code"."$display_session_counter";
    print "|".date("h:ia",$start);
    echo "|$minutes|";
    
    print "$session[title]";
    
    echo "|$session_type";

    print "<br>";

// THIS  IS THE SESSION LOOP WITH DETAILS - functions-events.php
   
} else{

    print "<h3>$code"."$display_session_counter <a href='/wp-admin/post.php?action=edit&post=$session_id' target='_blank'> $session[title]</a></h3>";
    
    if($session_type && $session_type != 'SESSION TYPE NOT SET'){
        echo ucfirst($session_type)."<br>";
    }
    
    print "Start: ".date("h:ia",$start);
    if($end){
        echo " - ".date("h:ia",$end);
    }
    echo "<br>";
    
    if($minutes > 0 || $seconds > 0){
        echo "Length: ";
        if($minutes > 0){
            echo "$minutes minute";
            if($minutes > 1){
                echo "s";
            }
        }
        if($seconds > 0){
            if($minutes > 0){
                echo " ";
            }
            echo "$seconds seconds";
        }
        echo "<br>";
    }
   
  



    if($session['post']->post_title == 'Break'){
      //  continue;
    }
    ros_items($session, $event_title);
    print"<hr>";
}
 
 $start=@$end;

 /*if($duration>0){
        if($duration != ''){
           $start+=intval(trim($duration));
          }
    }*/
 if(@$session['meta']['event_script_url'][0] != ''){
    $event_script_url = $session['meta']['event_script_url'][0];
    print "<a href='$event_script_url' target='_blank'>Script</a><br>";
 }              
           if(@$session['meta']['event_reel_url'][0] != ''){
                    $event_reel_url = $session['meta']['event_reel_url'][0];
                    print "<a href='$event_reel_url' target='_blank'>Reel</a><br>";
                }
        $speaker_emails = [];
        
        $moderator = '';
        $moderator_email = '';
        $with = '';
     
        // var_dump($session);
        
        //*speakerS
       

        //SPEAKER LEVEL
        $session_counter++;
    }
    
    if(@$_GET['sheet'] && $i == array_key_last($ros)){
        print "</pre>";
    }
    if(@$_GET['table']){
        print "</table>";
    }
    
       print "<hr>";
       if(@$_GET['moderators']==1){

       print '<BR>ALL Emails:<BR><textarea cols="80" rows="2">'.implode(",",$speaker_emails).'</textarea><BR>';
       }



       $start = $start + (@$session['meta']['duration'][0]*60);
     
     
  
   
    
}

foreach($invitation_statuses as $is => $invitation_status){
    if($is === 'no-status'){
        continue;
    }
    print "<span class='$is'>".strtoupper(str_replace("_","",$is)).'</span><br><ol class="$is">';
            foreach($invitation_status as $key=>$status){
                extract($status);
                extract($speaker_info);
                
                print "<li class='$is'>$session_name | <a target='_new' href='/wp-admin/post.php?action=edit&post=$id'><span class='$is'>".$speaker."</span></a>";
                if($guest_type && $guest_type != ''){
                    print " $guest_type";
                }
                if($email !=""){
                    print " | <a class='email' href='mailto:$email'>$email</a>";
                } else {
                    print "NO EMAIL";
                }
                if($linkedin !=""){
                    print " | <a href='$linkedin' target='_blank'><i class='fa-brands fa-linkedin'></i></a>";
                }
                if($email !=""){
                    print " | <a href='$twitter' target='_blank'><i class='fa-brands fa-x-twitter'></i></a>";
                }
            //    print "<span class='status-notes'>";
                if($point_of_contact !=""){
                    print " | $point_of_contact";
                }
                
                if($notes!=""){
                    print " | $notes";
                }
                print "</span></li>";

            }
            print '</ol><hr>';

        }









//var_dump($invitation_statuses);
$_REQUEST['status'] = [
    'calendar_sent'=>[],
    'calendar_confirmed'=>[],
    'signed_release'=>[],
    
];


?>


<legend class="invite-legend">
    <strong style="color:#fff">LEGEND - INVITATION COLOR CODES</strong>
    <li class="no-status">NO STATUS</li>
    <li class="team">Team</li>
    <li class="needs-invite">Needs Invitation</li>
    <li class="invited">Invited</li>
    <li class="agreed">Agreed</li>
    <li class="registered-no-release">Registered (release not signed)</li>
    <li class="registered">Registered</li>
    <li class="calendar-sent">Calendar Sent</li>
    <li class="calendar-sent-no-registration">Calendar Sent (no Registration)</li>
    <li class="calendar-sent-no-release">Calendar Sent (no Release Signed)</li>
    <li class="confirmed-no-registration">Calendar Confirmed (no Registration)</li>
    <li class="confirmed-no-release">Calendar Confirmed (no Release Signed)</li>
    <li class="prerecord">Session Prerecorded</li>    
    <li class="complete">Registration Confirmed and Complete</li>

</legend>
