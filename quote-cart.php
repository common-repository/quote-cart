<?php
/*

Plugin Name: Quote Cart

Plugin URI: http://www.churchmediaresource.com/web/quote-cart

Description: Works a bit like a shopping cart but there is no register or payment. Instead the visitor selects products they would like a price quote for. The products they select appear in a list in a widget which the user then sends as a form to a designated email address. Go to Settings > Quote Cart to view instructions for adding product links to your pages / posts or go to <a href="http://www.churchmediaresource.com/web/quote-cart"> ChurchMediaResource.com </a>

Version: 1.0

Author: ChurchMediaResource.com

Author URI: http://www.churchmediaresource.com/web/quote-cart

*/
session_start();
global $table_name, $plugin_version;

$table_name = $wpdb->prefix . 'favorite_quote';
$table_quotes = $wpdb->prefix . "user_quotes";
$plugin_version = "1.2";


register_activation_hook( __FILE__, 'my_fav_quote_install'); // starts the installation of the database if the plugin is activated
add_action('admin_menu', 'my_fav_quote_add_menu');
//my_fav_quote_opt_in();
if(isset($_GET["rem-post"]) || isset($_GET['fav-post'])) {
	add_action('init', 'my_fav_quote_modify_database'); // calls function my_fav_quote_modify_database() at the initialisation of each page
}
if(isset($_GET["rem-quote"]) || isset($_GET['fav-quote'])) {
add_action('init', 'my_fav_quote_modify_database2'); // calls function my_fav_quote_modify_database() at the initialisation of each page
}

add_action('widgets_init', 'widget_my_fav_quote_init');



function my_fav_quote_install() {

global $wpdb, $version, $table_name;

$table_name = $wpdb->prefix . 'favorite_quote';
$table_quotes = $wpdb->prefix . 'user_quotes';
$table_add_quotes = $wpdb->prefix.'add_fav_quote';
# Create DB table

$sql = "CREATE TABLE IF NOT EXISTS $table_name ("
       ." id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,"
	   ." user_id MEDIUMINT NULL,"
	   ." post_id TEXT NULL,"
	   ." cookie_id int(11) NULL,"
	   ." quote_id TEXT NULL,"
	   ." UNIQUE KEY id (id)"
	   ." ) TYPE = MYISAM;";
$result = $wpdb->query($sql);

$sql2 = "CREATE TABLE IF NOT EXISTS $table_quotes (
 		id bigint(11) NOT NULL auto_increment,
 		created_date datetime NOT NULL,
 		ip varchar(50) NOT NULL default '',
 		name varchar(50) NOT NULL default '',
 		email varchar(100) NOT NULL default '',
		quotes text NULL,
  		UNIQUE KEY id (id)
		);";
$result2 = $wpdb->query($sql2);
		
$blogname = get_option('blogname');
add_option('my_fav_quote_email_from', get_option('admin_email') );
add_option('my_fav_quote_email_subject', "$blogname - My Quotes");
add_option('my_fav_quote_msg_fail', "<p>Failed sending to e-mail address.</p>");
add_option('my_fav_quote_msg_sent', "<p>Request Sent.</p>");

$sql3 = "CREATE TABLE IF NOT EXISTS $table_add_quotes ("
       ." id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,"
	   ." title varchar(255) NULL,"
	   ." url varchar(255) NULL,"
	   ." PRIMARY KEY (id)"
	   ." ) TYPE = MYISAM;";
$result3 = $wpdb->query($sql3);

}# end my_fav_quote_install

# ----------------------------------
# Add Reomve Quote Link

function my_fav_quote_the_link($var="") { // function to display the "add post to favs" link in front end

# ----------------------------------

//if (current_user_can('level_0')){ // if the user is logged in

global $user_ID, $post, $wpdb, $table_name; // get current user id and current post id



//variables we are looking for

$defaults = array(

		'add_link' => 'Add this post to your favorite post list', 

		'remove_link' => 'Remove this post from your favorite post list'

	);



	$endvar = wp_parse_args( $var, $defaults );	

	extract( $endvar, EXTR_SKIP );

	$post_ID = $post->ID; 

	$mod_url = my_fav_quote_create_link_url();

	if($user_ID != 0)
		$data = $wpdb->get_var("SELECT post_id FROM $table_name WHERE user_id = '$user_ID'");
	else
	{
		
		$data = $wpdb->get_var("SELECT post_id FROM $table_name WHERE cookie_id = '".$_SESSION['SET_QUOTES']."'");
	}
	
		if (!(preg_match("/(^".$post_ID.",|,".$post_ID."$|^".$post_ID."$|,".$post_ID.",)/",$data))){

			echo "<a href='".$mod_url."fav-post=$post_ID'>".$endvar['add_link']."</a>"; // creates a link for adding the post to the database

		} else{

			echo "<a href='".$mod_url."rem-post=$post_ID'>".$endvar['remove_link']."</a>"; // creates a link for removing the post to the database

		}

	//}


} # end my_fav_quote_the_link


# ----------------------------------
# Create Add Remove Quote Link for post
function my_fav_quote_create_link_url(){

# ----------------------------------



	$current_url = $_SERVER['REQUEST_URI'];

	if(preg_match("/(\?|&)?(rem-post=|fav-post=)[0-9]+$/",$current_url)){

		$current_url = preg_replace("/(\?|&)?(rem-post=|fav-post=)[0-9]+/","",$current_url);

	} else{

		$current_url = preg_replace("/(rem-post=|fav-post=)[0-9]+&?/","",$current_url);

	}

	preg_match("/\?/", $current_url) == 0 ? $mod_url= $current_url."?" :  $mod_url= $current_url."&"; // checks if there is a ? in the URL

	return $mod_url;

	


} # end my_fav_quote_the_link


# ----------------------------------
# Create Add Remove Quote Link for quote which are added in shortcode
function my_fav_quote_create_link_url2(){

# ----------------------------------



	$current_url = $_SERVER['REQUEST_URI'];

	if(preg_match("/(\?|&)?(rem-quote=|fav-quote=)[0-9]+$/",$current_url)){

		$current_url = preg_replace("/(\?|&)?(rem-quote=|fav-quote=)[0-9]+/","",$current_url);

	} else{

		$current_url = preg_replace("/(rem-quote=|fav-quote=)[0-9]+&?/","",$current_url);

	}

	preg_match("/\?/", $current_url) == 0 ? $mod_url= $current_url."?" :  $mod_url= $current_url."&"; // checks if there is a ? in the URL

	return $mod_url;

	


} # end my_fav_quote_the_link2


# ----------------------------------
# insert update Quote for post
function my_fav_quote_modify_database(){

# ----------------------------------

if ((current_user_can('level_0') || isset($_SESSION["SET_QUOTES"])) || (isset($_GET['fav-post']) || isset($_GET['rem-post']))){


		global $user_ID, $wpdb, $table_name;

		if(current_user_can('level_0'))
		{
			$user_ID = $user_ID;
		}
		else
		{
			if(isset($_SESSION['SET_QUOTES']) && $_SESSION['SET_QUOTES'] !='') {
				$cookie = $_SESSION['SET_QUOTES'];
				
			}
			else
			{
				$random_number ='';
				for($i=0;$i<=6;$i++)
				{
					$random_number .= rand(0,9);
				}
				
				setcookie("SET_QUOTES",$random_number);
			}
		}
		$post_ID = $wpdb->escape($_GET['fav-post']);

		$remove_post_ID = $wpdb->escape($_GET['rem-post']);


		
		if($_SESSION['SET_QUOTES'] != '')
			$cookie = $_SESSION['SET_QUOTES'];
		else
		{
			$_SESSION['SET_QUOTES'] = $random_number;
			$cookie = $_SESSION['SET_QUOTES'];
		}
		
	if(preg_match("/^[0-9]+$/",$post_ID) || preg_match("/^[0-9]+$/",$remove_post_ID)){
	if($user_ID != 0)
			$data = $wpdb->get_results("SELECT * FROM $table_name WHERE user_id = '$user_ID'");
		else
		{
			$data = $wpdb->get_results("SELECT * FROM $table_name WHERE cookie_id = '$cookie'");
		}
	
		$postData = $data[0]->post_id;
		if ($post_ID != ""){ // if the user wants to add a post

			// if the user wants to add a post
			if (count($data) > 0 )
			{
				if (!(preg_match("/(^".$post_ID.",|,".$post_ID."$|^".$post_ID."$|,".$post_ID.",)/",$postData))){

					if($postData != "")
						$postData .= ",".$post_ID;
					else
						$postData .= $post_ID;
					
					if($user_ID != 0)
						$wpdb->query("UPDATE $table_name SET post_id ='$postData' WHERE user_id='$user_ID'");
					else
						$wpdb->query("UPDATE $table_name SET post_id ='$postData' WHERE cookie_id='$cookie'");

				}
				

			}else{

				if($user_ID != 0)
					$wpdb->query("INSERT INTO $table_name (user_id, post_id) VALUES ('$user_ID','$post_ID')"); 
				else
					$wpdb->query("INSERT INTO $table_name (post_id,cookie_id) VALUES ('$post_ID','$cookie')");

			}

		}

	

		if ($remove_post_ID != "" && $postData != ""){ // if the user wants to remove a post

			if ($postData == $remove_post_ID && $data[0]->quote_id == ''){
				if($user_ID != 0)
					$wpdb->query("DELETE FROM $table_name WHERE user_id = '$user_ID'");
				else
					$wpdb->query("DELETE FROM $table_name WHERE cookie_id = '$cookie'");

			} else {

				$postData = preg_replace("/(^".$remove_post_ID.",|,".$remove_post_ID."$|^".$remove_post_ID."$)/","", $postData);

				$postData = preg_replace("/,".$remove_post_ID.",/",",", $postData);
				if($user_ID != 0)
					$wpdb->query("UPDATE $table_name SET post_id ='$postData' WHERE user_id='$user_ID'");
				else
					$wpdb->query("UPDATE $table_name SET post_id ='$postData' WHERE cookie_id='$cookie'");

			}

		}

	}

}

} # end my_fav_quote_modify_database


# ----------------------------------
# insert update Quote for quote  which are added in shortcode
function my_fav_quote_modify_database2(){

# ----------------------------------

if ((current_user_can('level_0') || isset($_SESSION["SET_QUOTES"])) || (isset($_GET['fav-quote']) || isset($_GET['rem-quote']))){
	global $user_ID, $wpdb, $table_name;
	
	if(isset($_SESSION['SET_QUOTES']) && $_SESSION['SET_QUOTES'] !='') 
	{
		$cookie = $_SESSION['SET_QUOTES'];
	}
	else
	{
		$random_number ='';
		for($i=0;$i<=6;$i++)
		{
			$random_number .= rand(0,9);
		}
		
		setcookie("SET_QUOTES",$random_number);
	}
	if($_SESSION['SET_QUOTES'] != '')
		$cookie = $_SESSION['SET_QUOTES'];
	else
		$cookie = $random_number;
	
	$post_ID = $wpdb->escape($_GET['fav-quote']);
	$remove_post_ID = $wpdb->escape($_GET['rem-quote']);

	if(preg_match("/^[0-9]+$/",$post_ID) || preg_match("/^[0-9]+$/",$remove_post_ID))
	{
		if($user_ID != 0)
			$data = $wpdb->get_results("SELECT * FROM $table_name WHERE user_id = '$user_ID'");
		else
			$data = $wpdb->get_results("SELECT * FROM $table_name WHERE cookie_id = '$cookie'");
		
		$quoteData = $data[0]->quote_id;
		
		if ($post_ID != "")
		{ 
			// if the user wants to add a post
			if (count($data) > 0 )
			{
				if (!(preg_match("/(^".$post_ID.",|,".$post_ID."$|^".$post_ID."$|,".$post_ID.",)/",$quoteData)))
				{
					if($quoteData != "")
						$quoteData .= ",".$post_ID;
					else
						$quoteData .= $post_ID;
					
					if($user_ID != 0)
						$wpdb->query("UPDATE $table_name SET quote_id ='$quoteData' WHERE user_id='$user_ID'");
					else
						$wpdb->query("UPDATE $table_name SET quote_id ='$quoteData' WHERE cookie_id='$cookie'");
				}
			}
			else
			{
				if($user_ID != 0)
					$wpdb->query("INSERT INTO $table_name (user_id, quote_id) VALUES ('$user_ID','$post_ID')"); 
				else
					$wpdb->query("INSERT INTO $table_name (quote_id,cookie_id) VALUES ('$post_ID','$cookie')");
			}
		}

		if ($remove_post_ID != "" && $quoteData != ""){ // if the user wants to remove a post

			if ($quoteData == $remove_post_ID && $data[0]->post_id == ''){
				if($user_ID != 0)
					$wpdb->query("DELETE FROM $table_name WHERE user_id = '$user_ID'");
				else
					$wpdb->query("DELETE FROM $table_name WHERE cookie_id = '$cookie'");

			} else {

				$quoteData = preg_replace("/(^".$remove_post_ID.",|,".$remove_post_ID."$|^".$remove_post_ID."$)/","", $quoteData);
			
				$quoteData = preg_replace("/,".$remove_post_ID.",/",",", $quoteData);
				if($user_ID != 0)
					$wpdb->query("UPDATE $table_name SET quote_id ='$quoteData' WHERE user_id='$user_ID'");
				else
					$wpdb->query("UPDATE $table_name SET quote_id ='$quoteData' WHERE cookie_id='$cookie'");

			}

		}

	}

}

} # end my_fav_quote_modify_database2


# ----------------------------------
# display Quote list in widget form
function my_fav_quote_display($var="",$show_user=""){

# ----------------------------------

global $wpdb, $user_ID, $table_name;



if($show_user == ""){

	$query_user = $user_ID;

}else{

	$query_user = $show_user;

}



if ((current_user_can('level_0') || isset($_SESSION["SET_QUOTES"])) || (isset($_GET['fav-post']) || isset($_GET['rem-post']))){

$table_name2 = $wpdb->prefix . 'posts';







$sql = "";

$fav_post ="";



$defaults = array(

		'title' => '', 

		'display' => 'list',

		'remove_link' => 'remove',

		'class' => 'my_fav_quote_favorites',

		'link_class' => 'my_fav_quote_link',

		'remove_link_class' => 'my_fav_quote_remove_link',

		'order_by' => 'ID'

	);

if($user_ID != 0)
	$data = $wpdb->get_var("SELECT post_id FROM $table_name WHERE user_id = '$user_ID'");
else
	$data = $wpdb->get_var("SELECT post_id FROM $table_name WHERE cookie_id = '".$_SESSION['SET_QUOTES']."'");



/*if ($data == ""){

	if($show_user == ""){

//echo "<p>You are logged in. Start adding products you like to your personal wishlist.</p>";

	}else{

//echo "<p>This user has not marked any posts as favorite posts yet.</p>";	

	}

}else{*/



	$dataarray = explode(',',$data);

		foreach ($dataarray as $entry){

							$sql .= "OR ID = '$entry' ";

						}	

						

		$endvar = wp_parse_args( $var, $defaults );

		extract( $endvar, EXTR_SKIP );

						

		$sql = preg_replace("/^OR./","", $sql);	

		$order = $endvar['order_by'];

		$my_posts = $wpdb->get_results("SELECT * FROM $table_name2 WHERE $sql ORDER BY $order");

		$mod_url = my_fav_quote_create_link_url();

		

		

		

		if ($endvar['display'] == "list"){

		$wrap_before = "<ul class='".$endvar['class']."'>";

		$wrap_after = "</ul>";

		$entry_before = "<li>";

		$entry_after = "</li>";

		} else if ($endvar['display'] == "div"){

		$wrap_before = "<div class='".$endvar['class']."'>";

		$wrap_after = "</div>";

		$entry_before = "<p>";

		$entry_after = "</p>";

		}

		

		foreach ($my_posts as $entry){
			$fav_post .= $entry_before."<a href='".get_permalink($entry->ID)."' title='".$entry->post_title."' class='".$endvar['link_class']."'>".$entry->post_title."</a>&nbsp;&nbsp;";

			if($show_user == "" || $show_user == $user_ID){

			$fav_post .= "<a href='".$mod_url."rem-post=".$entry->ID."' class='".$endvar['remove_link_class']."'>".$endvar['remove_link']."</a>".$entry_after;

			}

		}

		

		

		//ausgabe

		/*echo $title;

		echo $wrap_before;

		echo $fav_post;

		echo $wrap_after;*/
		
		$text ='';
		
		$text .= $title;

		$text .= $wrap_before;

		$text .= $fav_post;

		$text .= $wrap_after;
	
		return $text;
	//}

}

# ----------------------------------

} # end my_fav_quote_display

# ----------------------------------
# display Quote list in mail which are added in shortcode
function my_fav_quote_display2($var="",$show_user=""){

# ----------------------------------

global $wpdb, $user_ID, $table_name;



if($show_user == ""){

	$query_user = $user_ID;

}else{

	$query_user = $show_user;

}



if (current_user_can('level_0') || $show_user != "" || isset($_SESSION['SET_QUOTES']) || (isset($_GET['fav-post']) || isset($_GET['rem-post']))){

$table_name2 = $wpdb->prefix . 'posts';







$sql = "";

$fav_post ="";



$defaults = array(

		'title' => '', 

		'display' => 'list',

		'remove_link' => 'remove',

		'class' => 'my_fav_quote_favorites',

		'link_class' => 'my_fav_quote_link',

		'remove_link_class' => 'my_fav_quote_remove_link',

		'order_by' => 'ID'

	);

if($query_user != 0)
	$data = $wpdb->get_var("SELECT post_id FROM $table_name WHERE user_id = '$query_user'");
else
	$data = $wpdb->get_var("SELECT post_id FROM $table_name WHERE cookie_id = '".$_SESSION['SET_QUOTES']."'");





if ($data == ""){

	if($show_user == ""){

//echo "<p>You are logged in. Start adding products you like to your personal wishlist.</p>";

	}else{

//echo "<p>This user has not marked any posts as favorite posts yet.</p>";	

	}

}else{



	$dataarray = explode(',',$data);
	$sql .= "ID IN ($data) ";
		/*foreach ($dataarray as $entry){

							$sql .= "OR ID = '$entry' ";

						}	*/

						

		$endvar = wp_parse_args( $var, $defaults );

		extract( $endvar, EXTR_SKIP );

						

		$sql = preg_replace("/^OR./","", $sql);	

		$order = $endvar['order_by'];

		$my_posts = $wpdb->get_results("SELECT * FROM $table_name2 WHERE $sql ORDER BY $order");

		$mod_url = my_fav_quote_create_link_url();

	

		

		

		if ($endvar['display'] == "list"){

		$wrap_before = "<ul class='".$endvar['class']."'>";

		$wrap_after = "</ul>";

		$entry_before = "<li>";

		$entry_after = "</li>";

		} else if ($endvar['display'] == "div"){

		$wrap_before = "<div class='".$endvar['class']."'>";

		$wrap_after = "</div>";

		$entry_before = "<p>";

		$entry_after = "</p>";

		}

		
		$i=0;
		
		foreach ($my_posts as $entry){
			$i++;
			
			$fav_post .= $entry->post_title;
			if($i != count($my_posts))
				$fav_post .="<br>";

			

		}

		

		

		//ausgabe
		$text ='';
		
		//$text .= $title;

		//$text .= $wrap_before;

		$text .= $fav_post;

		//$text .= $wrap_after;
	
		return $text;
	}

}

# ----------------------------------

} # end my_fav_quote_display2


# ----------------------------------
# display Quote list in widget form which are added in shortcode
function my_fav_quote_display3($var="",$show_user=""){

# ----------------------------------

global $wpdb, $user_ID, $table_name;


if($show_user == ""){

	$query_user = $user_ID;

}else{

	$query_user = $show_user;

}



if (current_user_can('level_0') || $show_user != "" || isset($_SESSION['SET_QUOTES']) || (isset($_GET['fav-quote']) || isset($_GET['rem-quote']))){

$table_name2 = $wpdb->prefix . 'add_fav_quote';

$sql = "";

$fav_post ="";


$defaults = array(

		'title' => '', 

		'display' => 'list',

		'remove_link' => 'remove',

		'class' => 'my_fav_quote_favorites',

		'link_class' => 'my_fav_quote_link',

		'remove_link_class' => 'my_fav_quote_remove_link',

		'order_by' => 'ID'

	);


if($query_user != 0)
	$data = $wpdb->get_var("SELECT quote_id FROM $table_name WHERE user_id = '$query_user'");
else
	$data = $wpdb->get_var("SELECT quote_id FROM $table_name WHERE cookie_id = '".$_SESSION['SET_QUOTES']."'");



	$dataarray = explode(',',$data);

		foreach ($dataarray as $entry){

							$sql .= "OR id = '$entry' ";

						}	

						

		$endvar = wp_parse_args( $var, $defaults );

		extract( $endvar, EXTR_SKIP );

						

		$sql = preg_replace("/^OR./","", $sql);	

		$order = $endvar['order_by'];

		$my_posts = $wpdb->get_results("SELECT * FROM $table_name2 WHERE $sql ORDER BY $order");

		$mod_url = my_fav_quote_create_link_url2();

		

		

		

		if ($endvar['display'] == "list"){

		$wrap_before = "<ul class='".$endvar['class']."'>";

		$wrap_after = "</ul>";

		$entry_before = "<li>";

		$entry_after = "</li>";

		} else if ($endvar['display'] == "div"){

		$wrap_before = "<div class='".$endvar['class']."'>";

		$wrap_after = "</div>";

		$entry_before = "<p>";

		$entry_after = "</p>";

		}

		

		foreach ($my_posts as $entry){
			$fav_post .= $entry_before."<a href='".$entry->url."' title='".$entry->post_title."' class='".$endvar['link_class']."'>".$entry->title."</a>&nbsp;&nbsp;";

			if($show_user == "" || $show_user == $user_ID){

			$fav_post .= "<a href='".$mod_url."rem-quote=".$entry->id."' class='".$endvar['remove_link_class']."'>".$endvar['remove_link']."</a>".$entry_after;

			}

		}

		
		$text ='';
		
		$text .= $title;

		$text .= $wrap_before;

		$text .= $fav_post;

		$text .= $wrap_after;
	
		return $text;
	

}

# ----------------------------------

} # end my_fav_quote_display3


# ----------------------------------
# display Quote list in mail

function my_fav_quote_display4($var="",$show_user=""){

# ----------------------------------

global $wpdb, $user_ID, $table_name;



if($show_user == ""){

	$query_user = $user_ID;

}else{

	$query_user = $show_user;

}



if (current_user_can('level_0') || $show_user != "" || isset($_SESSION['SET_QUOTES']) || (isset($_GET['fav-quote']) || isset($_GET['rem-quote']))){

$table_name2 = $wpdb->prefix . 'add_fav_quote';


$sql = "";

$fav_post ="";



$defaults = array(

		'title' => '', 

		'display' => 'list',

		'remove_link' => 'remove',

		'class' => 'my_fav_quote_favorites',

		'link_class' => 'my_fav_quote_link',

		'remove_link_class' => 'my_fav_quote_remove_link',

		'order_by' => 'ID'

	);

if($query_user != 0)
	$data = $wpdb->get_var("SELECT quote_id FROM $table_name WHERE user_id = '$query_user'");
else
	$data = $wpdb->get_var("SELECT quote_id FROM $table_name WHERE cookie_id = '".$_SESSION['SET_QUOTES']."'");


	$dataarray = explode(',',$data);
	$sql .= "id IN ($data) ";
		/*foreach ($dataarray as $entry){

							$sql .= "OR ID = '$entry' ";

						}	*/

						

		$endvar = wp_parse_args( $var, $defaults );

		extract( $endvar, EXTR_SKIP );

						

		$sql = preg_replace("/^OR./","", $sql);	

		$order = $endvar['order_by'];

		$my_posts = $wpdb->get_results("SELECT * FROM $table_name2 WHERE $sql ORDER BY id");

		$mod_url = my_fav_quote_create_link_url();

	

		

		

		if ($endvar['display'] == "list"){

		$wrap_before = "<ul class='".$endvar['class']."'>";

		$wrap_after = "</ul>";

		$entry_before = "<li>";

		$entry_after = "</li>";

		} else if ($endvar['display'] == "div"){

		$wrap_before = "<div class='".$endvar['class']."'>";

		$wrap_after = "</div>";

		$entry_before = "<p>";

		$entry_after = "</p>";

		}

		
		$i=0;
		foreach ($my_posts as $entry){
			$i++;
			
			$fav_post .= $entry->title;
			if($i != count($my_posts))
				$fav_post .="<br>";

		}
		$text ='';
		
		//$text .= $title;

		//$text .= $wrap_before;

		$text .= $fav_post;

		//$text .= $wrap_after;
	
		return $text;
	

}

# ----------------------------------

} # end my_fav_quote_display4
# ----------------------------------

#WIDGET SETTINGS

# ----------------------------------

function widget_my_fav_quote_init(){

	if ( !function_exists('register_sidebar_widget') ) {

		return;

	}
function my_fav_quote_display_widget($args) {

			if ((current_user_can('level_0') || isset($_SESSION["SET_QUOTES"])) || (isset($_GET['fav-post']) || isset($_GET['rem-post'])) || (isset($_GET['fav-quote']) || isset($_GET['rem-quote']))){

          extract($args);

		
			$options  = get_option('my_fav_quote_display_widget');

			$title = empty( $options['my_fav_quote_title'] ) ? '' : $options['my_fav_quote_title'];
		  
			$out2 = '';
			if ( function_exists( 'my_fav_quote_display2' ) ){
			$out2 .= my_fav_quote_display2();
			}
			if ( function_exists( 'my_fav_quote_display4' ) ){
				$out2 .= my_fav_quote_display4();
			}
			if(!empty($out2))
			{
				echo $before_widget;

				echo $before_title;
	
				echo $title;			
	
				echo $after_title;
	
				my_fav_quote_opt_in();
	
				echo $after_widget;
			}
		    

          }

	  }

function my_fav_quote_control_display_widget(){

		$options = $newoptions = get_option('my_fav_quote_display_widget');

		

		if ( $_POST['my_fav_quote_submit'] ) {

		$newoptions['my_fav_quote_title'] = strip_tags(stripslashes($_POST['my_fav_quote_title']));

		}

		if ( $options != $newoptions ) {

		$options = $newoptions;

		update_option('my_fav_quote_display_widget', $options);

		}

		

		

		?>

		<p>

<input id="my_fav_quote_submit" type="hidden" value="1" name="my_fav_quote_submit"/>



<label for="my_fav_quote_title">Title: <small> (default is no title)</small></label>

<input id="my_fav_quote_title" class="widefat" type="text" value="<?php echo $newoptions['my_fav_quote_title']; ?>" name="my_fav_quote_title"/>

</p>


		

<?php }	 
	   register_sidebar_widget('Quote Cart', 'my_fav_quote_display_widget');

	   register_widget_control('Quote Cart', 'my_fav_quote_control_display_widget');



} # end widget_my_fav_quote_init



# ----------------------------------
# Quote general settings
function my_fav_quote_settings() {

	global $wpdb;

	$table_quotes = $wpdb->prefix . "user_quotes";

	// if $_GET['user_id'] set tden delete user from list
	if (isset($_GET['user_id'])) {
		$user_id = $_GET['user_id'];

		// Delete user from database
		$delete = "DELETE FROM " . $table_quotes .
				" WHERE id = '" . $user_id . "'";
		$result = $wpdb->query($delete);

		// Notify admin of delete
		echo '<div id="message" class="updated fade"><p><strong>';
		_e('Quote deleted.', 'my_fav_quote_domain');
		echo '</strong></p></div>';
	}
					
	// Get current options from database
	$email_from = stripslashes(get_option('my_fav_quote_email_from'));
	$email_subject = stripslashes(get_option('my_fav_quote_email_subject'));
	
	$msg_fail = stripslashes(get_option('my_fav_quote_msg_fail'));
	$msg_sent = stripslashes(get_option('my_fav_quote_msg_sent'));

	// Update options if user posted new information
	if( $_POST['process'] == 'edit' ) {
		// Read from form
		$email_from = stripslashes($_POST['my_fav_quote_email_from']);
		$email_subject = stripslashes($_POST['my_fav_quote_email_subject']);
		
		$msg_fail = stripslashes($_POST['my_fav_quote_msg_fail']);
		$msg_sent = stripslashes($_POST['my_fav_quote_msg_sent']);

		// Save to database
		update_option('my_fav_quote_email_from', $email_from );
		update_option('my_fav_quote_email_subject', $email_subject);
		
		update_option('my_fav_quote_msg_fail', $msg_fail);
		update_option('my_fav_quote_msg_sent', $msg_sent);

		//notify change
		echo '<div id="message" class="updated fade"><p><strong>';
		_e('Settings saved.', 'my_fav_quote_domain');
		echo '</strong></p></div>';
	}
	
?>

<div class="wrap">
<a href="quote-cart/quote-cart/options-general.php?page=quote-cart/quote-cart.php">Show all</a><br/><br/>
<?php

	if ($users = $wpdb->get_results("SELECT * FROM $table_quotes ORDER BY `id` DESC")) {
		$user_no=0;
		$url = get_bloginfo('wpurl') . '/wp-admin/options-general.php?page=quote-cart/' .
			basename(__FILE__);
?>
<table class="widefat">
<thead>    
<tr>
<td scope="col">ID</td>
<td scope="col">Created Date</td>
<td scope="col">IP</td>
<td scope="col">Name</td>
<td scope="col">E-mail</td>
<td scope="col">Quotes</td>
<td scope="col">Action</td>
</tr>
</thead>
<tbody>
<?php
		$url = $url . '&amp;user_id=';
		$offset=$_GET[offset];
		my_fav_quote_checkValid($offset);
		
		if($offset =='')
			$offset = 0;
			
		$limit = 25;

		$pagenumber =intval(count($users)/$limit);
		if(count($users)%$limit)
		{
			$pagenumber++;
		}

		//paging
		echo("Page: ");
		for($i=1;$i<=$pagenumber;$i++)
		{
			$newpage=$limit*($i-1);

			if($offset!=$newpage)
			{
				echo "[<a href='options-general.php?page=quote-cart/quote-cart.php&type=".$_GET['type']. "&offset=".$newpage."'>$i</a>]";
			}else
			{
				echo "[$i]";
			}
		}

		for($i=$offset;$i<$offset+$limit;$i++)
		{
		
			$user = $users[$i];
			//check if we need to print
			if(!$user->created_date)
				continue;
					
			if ($user_no&1) {
				echo "<tr class=\"alternate\">";
			} else {
				echo "<tr>";
			}
			$user_no=$user_no+1;
			echo "<td>$user->id</td>";
			echo "<td>" . $user->created_date . "</td>";
			echo "</td>";
			echo "<td>$user->ip</td>";
			echo "<td>$user->name</td>";
			echo "<td>$user->email</td>";
			echo "<td>$user->quotes</td>";
			echo "<td>$user->quotes2<a href=\"$url$user->id\" onclick=\"if(confirm('Are you sure you want to delete quote with ID $user->id?')) return; else return false;\">Delete</a></td>";
			echo "</tr>";
		}

		//paging
?>
</tbody>
</table>
<?php
		echo("Page: ");
		for($i=1;$i<=$pagenumber;$i++)
		{
			$newpage=$limit*($i-1);

			if($offset!=$newpage)
			{
				echo "[<a href='options-general.php?page=quote-cart/quote-cart.php&type=".$_GET['type']. "&offset=".$newpage."'>$i</a>]";
			}else
			{
				echo "[$i]";
			}
		}
?>
</div>
<?php
	}
?>
<div class="wrap">
<h2>Quote Cart</h2>
<p>To Create an "Add To Quote" link in your post place the following shortcode: <br>
  [addquote title="title here" url="http://www.yoururl.com]title here[/addquote]</p>
<p>To Insert an "Add To Quote" link in all your posts place this function in the appropriate template file  of your theme loop:<br>
my_fav_quote_the_link("add_link=Add to Quote&remove_link=Remove from Quote") </p>
<p>Don't Forget to place your "Quote Cart" widget where you want to display selected quotes and "Request Quote" form</p>
<p>For More Information visit<a href="http://www.churchmediaresource.com/web/quote-cart/"> Plugin Site</a></p>
<br></p>

<p>If you found this plugin useful please make a donation... thanks!
<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="ZA2P2DKCYXWAY">
<input type="image" src="https://www.paypalobjects.com/en_AU/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online.">
<img alt="" border="0" src="https://www.paypalobjects.com/en_AU/i/scr/pixel.gif" width="1" height="1">
</form>
</p>
<br>

<form method="post" action="">
    <input type="hidden" name="process" value="edit" />
    
    <table widtd="100%" cellspacing="2" cellpadding="2">
	<tr valign="top"> 
        <td scope="row" colspan=2>General Settings</td>
      </tr>
      <tr valign="top"> 
        <td scope="row">Send Form To (email address)</td>
        <td> 
            <input type="text" name="my_fav_quote_email_from" id="my_fav_quote_email_from" value="<?php echo $email_from; ?>" size="40" />
        </td>
      </tr>
      <tr valign="top"> 
        <td scope="row">Email Subject For User:</td>
        <td> 
          <input type="text" name="my_fav_quote_email_subject" id="my_fav_quote_email_subject" value="<?php echo $email_subject; ?>" size="40" />
        </td>
      </tr>
      <tr valign="top"> 
        <td scope="row" colspan=2>Messages</td>
      </tr>
      
      <tr valign="top"> 
        <td scope="row">Failed to send email:</td>
        <td> 
          <input type="text" name="my_fav_quote_msg_fail" id="my_fav_quote_msg_fail" value="<?php echo $msg_fail; ?>" size="40" />
        </td>
      </tr>
      <tr valign="top"> 
        <td scope="row">Success send email:</td>
        <td> 
          <input type="text" name="my_fav_quote_msg_sent" id="my_fav_quote_msg_sent" value="<?php echo $msg_sent; ?>" size="40" />
        </td>
      </tr>
    
    </table>

<p class="submit"><input type="submit" name="Submit" value="Update Settings &raquo;" /></p>
</form>
</div>

<?php 
}# end my_fav_quote_settings


function my_fav_quote_checkValid($str)
{
	$valid_string = "[\\\"\*\^\'\;\&\>\<]";
	if(ereg($valid_string,$str))
	{
		echo("<br/>Invalid value:".$str."<br>");
		echo("<a href='javascript:history.go(-1)'>Try again<a>.<br/>");
		return "";
	}
	else
	{
		return $str;
	}
}# end my_fav_quote_checkValid

# ----------------------------------
# insert quotes in database which are dispaly in admin panel

function my_fav_quote_opt_in() {

	global $wpdb;
	$table_quotes = $wpdb->prefix . "user_quotes";

	//trim the email
	if (empty($_POST['my_fav_quote_email'])) {

		$_POST['my_fav_quote_email'] = trim($_POST['my_fav_quote_email']);
		my_fav_quote_show_optin_form();
	} 
	else {
	
		$name = stripslashes($_POST['my_fav_quote_name']);
		$name  = my_fav_quote_checkValid($name );

		$email = stripslashes($_POST['my_fav_quote_email']);
		$email = my_fav_quote_checkValid($email);
		
		$message = $_POST['my_fav_quote_message'];
		//replace name		
		$find = array('/�/','/�/','/�/','/�/','/�/','/�/','/�/','/ /','/[:;]/');

		$replace = array('ae','oe','ue','ss','Ae','Oe','Ue','_','');

		$name = preg_replace ($find , $replace, strtolower($name));


		if($name == "" || $email == "")
			return;
		
		$my_fav_quote_custom_flds = "";
		if (!preg_match("/\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/", $email)) {
				echo "Email format is incorrect";
				my_fav_quote_show_optin_form();
		}
		else {
			$user_message = '';
			$admin_message = '';
			$quotes3 ='';
			if( $_SESSION['security_code'] == $_POST['security_code'] && !empty($_SESSION['security_code'] ) ) {

				$email_from = stripslashes(get_option('my_fav_quote_email_from'));

				$subject = stripslashes(get_option('my_fav_quote_email_subject'));
				$my_fav_quote_ip = my_fav_quote_getip();
				$subject_admin = "User Quotes";
				
				$quotes = my_fav_quote_display2();
				$quotes2 = my_fav_quote_display4();
				
				$user_message .= "Hello ".$name.",<br><br>";
				$user_message .= "Thank you for your Quote Requests.<br><br>
Your Quotes are :<br><br>";
				$user_message .= $quotes."<br>";
				$user_message .= $quotes2;
				$user_message .="<br><br>We will contact you soon";
				
				
				$admin_message .="Hello Admin,<br><br>";
				$admin_message .= "New quote request received from ".$name.".<br><br>Name : ".$name."<br>Email : ".$email."<br><br>Quotes are :<br><br>";
				$admin_message .= $quotes."<br>";
				$admin_message .= $quotes2;
				$admin_message .= "<br><br>Message : ".$message;
				$blogname = get_option('blogname');
				if($quotes != '' && $quotes2 !='')
					$quotes3 = $quotes."<br>";
				elseif($quotes != '')
					$quotes3 = $quotes;
				$quotes3 .= $quotes2;
				$header = "MIME-Version: 1.0" . "\r\n"
				. "Content-Type: text/html; charset=iso-8859-1" . "\r\nFrom: $email_from\n";
				
				if (mail($email,$subject,$user_message, $header)) {

							$query = "INSERT INTO " . $table_quotes . " 
								(created_date, ip, email, name, quotes) 
								VALUES (
								now(),
								'" . $my_fav_quote_ip . "',
								'" . $email . "',
								'" . $name . "',
								'".str_replace("<br>",",",$quotes3)."'	)";
						 	$result = $wpdb->query($query);
							//echo($query);
						mail($email_from,$subject_admin,$admin_message, $header);
						echo stripslashes(get_option('my_fav_quote_msg_sent'));
						
						//ob_start();					
						//$_SESSION["newslettername"] = $name;

						//ob_end_flush();
					} 
					else {
						echo stripslashes(get_option('my_fav_quote_msg_fail'));
					}
				
					unset($_SESSION['security_code']);
				return 0;
			} else {
				// Insert your code for showing an error message here
				echo 'Sorry, you have provided an invalid security code. Please try again.';
				
		   }
		   
		}
	}
}# end my_fav_quote_opt_in

# ----------------------------------
# display form in widget
	  
function my_fav_quote_show_optin_form() {	

	if (!empty($_POST['my_fav_quote_email'])) {
	
		my_fav_quote_opt_in();
	}
		$out2 = '';
		
		$out = '<form action="" method="post" id="requestQuote">';
		$out .= '<table width="100%">';
		$out .= '<tr><td>Name:*</td><td><input type="text" name="my_fav_quote_name" id="my_fav_quote_name"/></td></tr>';
		$out .= '<tr><td colspan=2></td></tr>';
		$out .= '<tr><td>Email:*</td><td><input type="text" name="my_fav_quote_email" id="my_fav_quote_email"/></td></tr>';
		$out .= '<tr><td colspan=2></td></tr>';
		$out .= '<tr><td>Message:</td><td><textarea name="my_fav_quote_message" id="my_fav_quote_message" cols=18></textarea></td></tr>';
		$out .= '<tr><td colspan=2></td></tr>';
		$out .= '<tr><td>Security code:*</td><td><img src='.get_bloginfo('wpurl').'/wp-content/plugins/quote-cart/captcha.php?width=50&height=25&characters=5" /></td></tr>';
		$out .= '<tr><td colspan=2></td></tr>';
		$out .= '<tr><td></td><td><input type="text" name="security_code" id="security_code" size="5"></td></tr>';
		$out .= '<tr><td colspan=2></td></tr>';

		$out .='<tr><td colspan="2">';
		if ( function_exists( 'my_fav_quote_display' ) ){
			$out .= my_fav_quote_display();
			
		}
		if ( function_exists( 'my_fav_quote_display3' ) ){
			$out .= my_fav_quote_display3();
		}
		
		
		$out .='</td></tr>';
		$out .= '<tr><td colspan=2 align=center><input type="submit" value="Request Quote" onclick="return chk_validation()" style="background-color:#000;color:#FFF;padding:5px;margin-top:10px;border:none;cursor:pointer;"/></td></tr>';
		
		$out .='</table></form>';
		echo $out;
		?>
			<script language="javascript" type="text/javascript">
				//<![CDATA[
				function validate_email(field,alerttxt)
				{
				
				  apos=field.indexOf("@");
				// alert(apos);
				  dotpos=field.lastIndexOf(".");
				   //alert(dotpos);
				  if (apos<1||dotpos-apos<2)
					{ return false;}
				  else {return true;}
				  
				}
				function chk_validation()
				{
					if(document.getElementById("my_fav_quote_name") && document.getElementById("my_fav_quote_name").value == '')
					{
						alert("Please Enter Name");
						document.getElementById("my_fav_quote_name").focus();
						return false;
					}
					
					if(document.getElementById("my_fav_quote_email").value == '')
					{
						alert("Please Enter Email");
						document.getElementById("my_fav_quote_email").focus();
						return false;
					}
					else
					{
					
					//alert(validate_email(document.getElementById("my_fav_quote_email").value,"Not a valid e-mail address!");
						if (validate_email(document.getElementById("my_fav_quote_email").value,"Please enter valid e-mail address!")==false)						{
						alert("Please enter valid e-mail address!");
						document.getElementById("my_fav_quote_email").focus();
						return false;
						}
					}
					if(document.getElementById("security_code").value == '')
					{
						alert("Please Enter Security Code");
						document.getElementById("security_code").focus();
						return false;
					}
					if(document.getElementById("quotes").value == '')
					{
						alert("Please add atleast one request quote");
						document.getElementById("quotes").focus();
						return false;
					}
					//return true;
				}

				
				
				//]]>
	
			</script>
<?php

}# end my_fav_quote_show_optin_form


# ----------------------------------
# Quote get ip address of user

function my_fav_quote_getip() {
	if (isset($_SERVER)) {
		if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
			$ip_addr = $_SERVER["HTTP_X_FORWARDED_FOR"];
		} 
		elseif (isset($_SERVER["HTTP_CLIENT_IP"])) {
			$ip_addr = $_SERVER["HTTP_CLIENT_IP"];
		} 
		else {
			$ip_addr = $_SERVER["REMOTE_ADDR"];
		}
	} 
	else {
		if ( getenv( 'HTTP_X_FORWARDED_FOR' ) ) {
			$ip_addr = getenv( 'HTTP_X_FORWARDED_FOR' );
		} 
		elseif ( getenv( 'HTTP_CLIENT_IP' ) ) {
			$ip_addr = getenv( 'HTTP_CLIENT_IP' );
		} 
		else {
			$ip_addr = getenv( 'REMOTE_ADDR' );
		}
	}
	
	return $ip_addr;
}# end my_fav_quote_show_optin_form

# ----------------------------------
# add  quotes from shortcode in database

function add_favorite_quotes($atts, $content = null) {
	extract(shortcode_atts(array(
        "title" => '',
       	"url" => ''
	), $atts));
	global $wpdb,$post,$user_ID;
	$table_name = $wpdb->prefix . 'favorite_quote';
	$table_name2 = $wpdb->prefix . 'add_fav_quote';
	$query = "SELECT * FROM $table_name2 WHERE title = '".$title."' AND url ='".$url."'";
	$result = $wpdb->get_results($query);
	//echo "<pre>";print_r($result);echo "</pre>";
	$count = count($result);
	//echo get_bloginfo('home');
	
	if($count > 0)
	{
		if($user_ID != 0)
			$query = "SELECT * FROM $table_name WHERE user_id = '".$user_ID."'";
		else
			$query = "SELECT * FROM $table_name WHERE cookie_id = '".$_SESSION['SET_QUOTES']."'";
		
		$result2 = $wpdb->get_results($query);
		
		if(count($result2) > 0 && $result2[0]->quote_id !='')
		{
			$array = explode(",",$result2[0]->quote_id);
			if(in_array($result[0]->id,$array))
				return "<a href='".$result[0]->url."' target='_blank'>".$content."</a>&nbsp;&nbsp;<a href='".my_fav_quote_create_link_url2()."rem-quote=".$result[0]->id."'>REMOVE FROM QUOTE</a>";
			else
				return "<a href='".$result[0]->url."' target='_blank'>".$content."</a>&nbsp;&nbsp;<a href='".my_fav_quote_create_link_url2()."fav-quote=".$result[0]->id."'>ADD TO QUOTE</a>";
		}
		else
			return "<a href='".$result[0]->url."' target='_blank'>".$content."</a>&nbsp;&nbsp;<a href='".my_fav_quote_create_link_url2()."fav-quote=".$result[0]->id."'>ADD TO QUOTE</a>";
	}
	else
	{
	$query = "INSERT INTO $table_name2 (title, url) 
								VALUES (
								'" . $title . "',
								'" . $url . "'
								)";
						 	$result = $wpdb->query($query);
	$inserted_id = $wpdb->insert_id;
	return "<a href='".$url."' target='_blank'>".$content."</a>&nbsp;&nbsp;<a href='".my_fav_quote_create_link_url2()."fav-quote=".$inserted_id."'>ADD TO QUOTE</a>";
	
	}
}# end add_favorite_quotes

# ----------------------------------
# create Request Quote link in  settings menu

function my_fav_quote_add_menu() {
	add_options_page('Quote Cart', 'Quote Cart', 6, __FILE__, 'my_fav_quote_settings' );
}



add_shortcode('addquote', 'add_favorite_quotes');

# ----------------------------------
# add remove link for quotes in post

function add_remove_quote_link($content)
{
	if(!is_page())
	{
	if ( function_exists( 'my_fav_quote_the_link' ) ){
		$quote_link = my_fav_quote_the_link("add_link=ADD TO QUOTE&remove_link=REMOVE FROM QUOTE");
	} 
	$content = $quote_link. $content ;
	
return $content;
}
}
?>