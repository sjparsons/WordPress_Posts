<?

include_once('wp_formatting.php');  // dependency for wordpress formatting.


/**
 * wp_posts
 * 
 * A class for accessing posts from WordPress installs.
 *
 * @package WordPress
 * @author Samuel Parsons
 **/
class wp_posts {
	
	const default_version = '3.0.1';
	const get_posts_categories_default = false;
	const get_posts_limit_default = 0; // no limit.
	const get_posts_order_default = 'time_desc';
	const get_posts_time_span_default = 'all';	
	const get_posts_meta_filter_default = false;
	const get_posts_exclude_sub_cats_default = false;	
	const get_posts_no_data_default = false;
	
	private $get_posts_order_allowed = array('time_desc', 'time_asc', 'title_desc', 'title_asc', 'modified_desc', 'modified_asc');
	private $get_posts_time_span_allowed = array('all'); // you can also use an array with a begin and end values defined.
	
	private $db = false;
	private $version = false;
	public $results = false;
	public $categories = array();
	public $categories_by_slug = array();
	public $categories_ids = array();
	public $categories_slugs = array();
	public $category_tree = array();
	public $logs = array();
	public $total_q_time =0;
	private $temp_children = array();
	
	private $tables = array(
		'comments' => '',
		'links' =>'',
		'options' => '',
		'postmeta' => '',
		'posts' => '',
		'terms' => '',
		'term_relationships' => '',
		'term_taxonomy' => '',
		);
		
	private $tables_mu = array(
		'blogs' => '',
		'blog_versions' => '',
		'registration_log' => '',
		'signups' => '',
		'site' => '',
		'sitecategories' => '',
		'sitemeta' => '',
		'usermeta' => '',
		'users' => ''
		);
	
	
	/**
	 * Constructs the wp_posts class.
	 * 
	 * @return void
	 * @param resource $connection  Should be MySQL connection to a database containing a WordPress install.
	 * @param bool|id $multi_user_blog_id  Optional id of wordpress blog if using a multisite install of WordPress. Default is false.
 	 * @param bool|array $categories  Optional categories to narrow results to. False or empty array means all categories. Default is array().
	 * @param bool|array $categories_exclude  Optional categories to exclude from results. False or empty array means exclude none. Default is array().
	 * @param string $table_prefix  Optionally specific the prefix before the WordPress tables. Default is 'wp'
	 * @author Samuel Parsons
	 **/
	function __construct($connection , $multi_user_blog_id=false, $categories=array(), $categories_exclude=array(), $table_prefix='wp') {
		$this->connection = $connection; 
		$this->setup_tables($multi_user_blog_id,$table_prefix);		
		$this->categories_to_include = $categories;
		$this->categories_to_exclude = $categories_exclude;
	}
	
	
	/**
	 * Fetches an array of posts from WordPress.
	 * You give a set of options in an associative array (no specific order needed).
	 * Following is a list of keys and values for this array, all other keys will be discarded. If a value is not present, blank or not one
	 * of the allowed values then the default is used.
	 * 		'categories' integer, string, or array of integers or strings, each referring to either a category id or slug respectively.
	 * 		'limit' integer or array of integers. If an integer, it refers to the upper limit of number of posts to return. If an array,
	 * 			it should have two items: the first the starting item in the results, the second the number of posts to retrieve.
	 * 		'order' string. Only the following are accepted.
	 * 			"time_asc", "time_desc" (default), "title_asc", "title_desc", "modified_desc", "modified_asc"
	 * 		'time_span' array
	 * 			If set, the array should include two strings that when you apply strtotime() yield a valid time. 
	 * 			The first string is the begin date, the second is the end date.
	 *		'meta_filter' associative array.
	 *   		If set, the assoc. array should have the following keys: 'key', 'value' (optional), 'type' (optional). 
	 *  		The 'key' key should point at a string corresponding with a meta key.
	 *  		The 'value' key, if set, should point at a string that we'll compare with the meta values.
	 *  		The 'type' key, if set, should point to a string that is one of these: 'like', '=', '>', '<'. 'like' is the default.
	 *  		If only key is set, then the query will get posts that have that key declared. 
	 *  		If key and value are set and type is not, then it will get posts that have that meta key LIKE the given meta value
	 *  		If key, value, and type are set, then it will get posts that have the meta key set such that it 
	 * 			matches the given value in the way specified.
	 *		'exclude_sub_cats' bool. When false (default) posts that are in sub categories of the ones specified will be retrieved in addition to the posts in the categories specified. Setting this to true reverses the behavior.
	 *		'no_data' bool, default false. If set true, then returns just the number of results.
	 *
	 * @return array
	 * @param array $options_user Options
	 * @author Samuel Parsons
	 **/
	public function get_posts( $options_user=array() ) {		
		$options = array(
			'categories' => self::get_posts_categories_default,
			'limit' => self::get_posts_limit_default,
			'order' => self::get_posts_order_default,
			'time_span' => self::get_posts_time_span_default,
			'meta_filter' => self::get_posts_meta_filter_default,
			'exclude_sub_cats' => self::get_posts_exclude_sub_cats_default,
			'no_data' => self::get_posts_no_data_default,
		);
 
		// deal with user-given options.
		if ($options_user['categories'] && is_array($options_user['categories'] ))		$options['categories'] = $options_user['categories'];
		else if ($options_user['categories'] ) 											$options['categories'] = array($options_user['categories']);
				
		if ($options_user['limit'] && is_array($options_user['limit']) && count($options_user['limit']) > 0)					
																					$options['limit'] = $options_user['limit'];
		else if ($options_user['limit'] && !is_array($options_user['limit']) )		$options['limit'] = array(0,$options_user['limit']);
		
		if ($options_user['order'] && in_array($options_user['order'],$this->get_posts_order_allowed) )					
			$options['order'] = $options_user['order'];
			
		if ($options_user['time_span'] && is_array($options_user['time_span']) )		$options['time_span'] = $options_user['time_span'];

		if ($options_user['meta_filter'] && is_array($options_user['meta_filter']) && array_key_exists('key',$options_user['meta_filter']) ) {
			$options['meta_filter'] = $options_user['meta_filter'];
		}
		
		if ( array_key_exists('exclude_sub_cats',$options_user) && $options['exclude_sub_cats'] != $options_user['exclude_sub_cats'] )
			$options['exclude_sub_cats'] = !$options['exclude_sub_cats'];
		
		if ( array_key_exists('no_data',$options_user) && $options['no_data'] != $options_user['no_data'] )
			$options['no_data'] = !$options['no_data'];

		// build the post_categories array that we'll use in the query.
		// ensure that each of the selected categories is a valid category.
		// get all the sub categories of a category if $exclude_sub_cats is false.
		$this->get_categories();
		$post_categories = array(); // the ones that we'll actually query with.
		if ($options['categories']) {
			foreach($options['categories'] as $cat_identifier) {
				$cat = false;
				if ( in_array($cat_identifier,$this->categories_ids) ) {
					$cat = $this->categories[$cat_identifier];
				} 
				else if ( in_array($cat_identifier,$this->categories_slugs) ) {
					$cat = $this->categories_by_slug[$cat_identifier];
				}
				if ($cat && !array_key_exists($cat['term_id'],$post_categories) )  {
					$post_categories[$cat['term_id']] = $cat;
					if (!$options['exclude_sub_cats']) {
						foreach($this->categories_children[$cat['term_id']] as $sub_cat_id) {
							if (!array_key_exists($sub_cat_id,$post_categories) ) {
								$post_categories[$sub_cat_id] = $this->categories[$sub_cat_id];
							}
						}
					}
				}
			}
		}
			

		// QUERY
		// categories query
		$cat_query = "";
		$first = true;
		foreach($post_categories as $id => $category) {
			$cat_query .= (($first)?"":" or ")."  {$this->tables['term_relationships']}.term_taxonomy_id = '{$category['term_taxonomy_id']}' ";
			$first = false;
		}
		// limit query
		$limit_query = "";
		if ($options['limit']){
			if (count($options['limit']) == 1) {
				$limit_query = " limit ".intval($options['limit'][0])." ";
			}
			else {
				$limit_query = " limit ".intval($options['limit'][0]).",".intval($options['limit'][1])." ";
			}
		} 
		
		// order query
		$order_query = "";
		if ($options['order'] == 'time_asc') 			$order_query = " order by post_date asc ";
		else if ($options['order'] == 'title_asc') 		$order_query = " order by post_title asc";
		else if ($options['order'] == 'title_desc') 	$order_query = " order by post_title desc ";
		else if ($options['order'] == 'time_desc') 		$order_query = " order by post_date desc ";
		else if ($options['order'] == 'modified_desc') 		$order_query = " order by post_modified desc ";		
		else if ($options['order'] == 'modified_asc') 		$order_query = " order by post_modified asc ";
		
		// time_span query
		$time_span_query = "";
		if ( $options['time_span']  && count($options['time_span']) >= 2) { // if given time span is an array, then we count the first entry as the begin datestr and the second as the end date str.
			$begin_time = strtotime($options['time_span'][0]);
			$end_time = strtotime($options['time_span'][1]);
			if ($begin_time !== false && $end_time !== false) 
				$time_span_query = " post_date >= '".date('Y-m-d H:i:s',$begin_time)."' and post_date <= '".date('Y-m-d H:i:s',$end_time)."' " ;
		}
		
		// meta filter query
		$meta_query = "";
		if ($options['meta_filter']) {
			$key = mysql_real_escape_string($options['meta_filter']['key'],$this->connection);
			if (array_key_exists('value',$options['meta_filter'])) {
				$value = mysql_real_escape_string($options['meta_filter']['value'],$this->connection);
				$type = 'like';
				if (array_key_exists('type',$options['meta_filter'])) {
					if ($options['meta_filter']['type'] == '>') 		$type = ">";
					else if ($options['meta_filter']['type'] == '<') 	$type = "<";
					else if ($options['meta_filter']['type'] == '=')  	$type = "=";									
				}
			}
			if ($value && $type == 'like') 		$meta_sub_query = " meta_key = '$key' and meta_value like '%$value%' ";
			else if ($value && $type == '>') 	$meta_sub_query = " meta_key = '$key' and meta_value > '$value' ";
			else if ($value && $type == '<') 	$meta_sub_query = " meta_key = '$key' and meta_value < '$value' ";
			else if ($value && $type == '=') 	$meta_sub_query = " meta_key = '$key' and meta_value = '$value' ";
			else 								$meta_sub_query = " meta_key = '$key' ";
			$meta_query = "
			inner join 
			( 	select post_id from {$this->tables['postmeta']} 
				where 	$meta_sub_query
			) as temp 
			on {$this->tables['posts']}.ID =  temp.post_id
			";
		}		
		
		// build the sql query.
		$get_query = "SELECT {$this->tables['posts']}.* from {$this->tables['posts']}";
		if ($cat_query) {
			$get_query .= ", (
			select distinct {$this->tables['term_relationships']}.object_id from {$this->tables['term_relationships']}
			where $cat_query
			) as rel \n";
		}
		if ($meta_query) {
			$get_query .= $meta_query;
		}
		
		$get_query .= (($cat_query)?" where {$this->tables['posts']}.ID = rel.object_id and ":" where ")
			." {$this->tables['posts']}.post_status = 'publish'
			and {$this->tables['posts']}.post_type = 'post' 
			and  {$this->tables['posts']}.post_date <= '".date('Y-m-d H:i:s')."' 
			"
			.(( $time_span_query )?" and $time_span_query ":"")
			."$order_query
			$limit_query";
		$get_result = $this->query($get_query);
		if (!$get_result) {
			echo mysql_error($this->connection);
			return false;
		}
		if ($options['no_data']===false) {
			$posts = array();
			while($row = mysql_fetch_assoc($get_result)) {
				$row['post_content_formatted'] = $this->format_content($row['post_content']);
				$posts[] = $row;
			}
			return $posts;
		}
		else {
			return mysql_num_rows($get_result);
		}
	}
	
	/**
	 * Retrieves a post given an post-ID or -slug.
	 * 
	 * @return array or false
	 * @param int|string $post_name_or_id  The ID or slug of a specific post to retrieve.
	 * @param int|string $date_string  (optional) If an integer, treated as a timestamp. If a string, then strtotime() will be applied to it.
	 *			By specifying a $date_string, the post will need to match this date in addition to the slug / ID
	 * @author Samuel Parsons
	 **/
	public function get_post( $post_name_or_id, $date_string=false  ) {
		/* you can enter either an ID or  post_name / slug as the first variable. Based on whether it's id, we'll determine the query.
		the date string can either be an in integer (seconds) or can be a string, and if it's a string will be converted to a time str.  */
		
		if (is_int($post_name_or_id)) $post_id = intval($post_name_or_id);
		else  $post_name = mysql_real_escape_string($post_name_or_id,$this->connection); 
		
		if (!is_int($date_string) && strtotime($date_string) !== false)  $date = strtotime($date_string);
		else if (is_int($date_string)) $date = intval($date_string);
		else $date = false;
		
		$post_query = "select * from {$this->tables['posts']} 
			where ".(($post_name)?" post_name = '$post_name' ":""). (($post_id)?" ID = '$post_id' ":"")."
			and post_type = 'post' 
			and post_status = 'publish'
			".(($date)?"and post_date like '".date('Y-m-d',$date)."%'":"")."
			limit 1
			";
		$post_result = $this->query($post_query); //mysql_query($post_query,$this->connection);
		if (!$post_result || mysql_num_rows($post_result) == 0) return false;
		$this_post = mysql_fetch_assoc($post_result);
		$this_post['post_content_formatted'] = $this->format_content($this_post['post_content']);
		return $this_post;
	}
	
	/**
	 * Retrieves a post ID given post-slug.
	 * 
	 * @return string or false
	 * @param string $post_name The slug of a specific post to retrieve.
	 * @param int|string $date_string  (optional) If an integer, treated as a timestamp. If a string, then strtotime() will be applied to it.
	 *			By specifying a $date_string, the post will need to match this date in addition to the slug / ID
	 * @author Samuel Parsons
	 **/
	public function get_post_id( $post_name, $date_string=false ) {
		/* returns the id given a slug / anme */
		
		$post_name = mysql_real_escape_string($post_name,$this->connection); 

		if (!is_int($date_string) && strtotime($date_string) !== false) $date = strtotime($date_string);
		else if (is_int($date_string)) $date = intval($date_string);
		else $date = false;
		
		$post_query = "select ID from {$this->tables['posts']} 
			where post_name = '$post_name' 
			and post_type = 'post' 
			and post_status = 'publish'
			".(($date)?"and post_date like '".date('Y-m-d',$date)."%'":"")."
			limit 1
			";
		$post_result = $this->query($post_query); //mysql_query($post_query,$this->connection);
		if (!$post_result || mysql_num_rows($post_result) == 0) return false;
		$this_post = mysql_fetch_assoc($post_result);
		return $this_post['ID'];
	}
	
	/**
	 * Retrieves the meta for a particular post.
	 * 
	 * @return array or false
	 * @param int $post_id The ID of a post.
	 * @param string $meta_name  (optional) If specified returns just the posts values for that meta name.
	 * @author Samuel Parsons
	 **/
	public function get_post_meta($post_id,$meta_name=false) {
		$post_id = mysql_real_escape_string($post_id,$this->connection);
		$meta_query="";
		if ($meta_name) {
			$meta_name = mysql_real_escape_string($meta_name,$this->connection);
			$meta_query = " and meta_key = '$meta_name' ";
		}
		$query = "SELECT meta_key, meta_value FROM {$this->tables['postmeta']} WHERE post_id = '$post_id' $meta_query order by meta_key asc";
		$result = $this->query($query); 
		if ($result && mysql_num_rows( $result) > 0 ) {
			if ($meta_name) {
				$metas = array();
				$row = mysql_fetch_assoc($result);
				return $row['meta_value'];
			}
			else {				
				while ($row = mysql_fetch_assoc($result)) {
					$metas[$row['meta_key']] = $row['meta_value'];
				}
				return $metas;				
			}
		}
		return false;
	}
	
	/**
	 * Returns an array of categories that a post is tagged as.
	 * 
	 * @return array or false
	 * @param int $post_id The ID of a post.
	 * @author Samuel Parsons
	 **/
	public function get_post_categories($post_id) {
		$post_id = mysql_real_escape_string($post_id,$this->connection);
		$query = "SELECT * from {$this->tables['terms']}, {$this->tables['term_taxonomy']} 
			inner join (select term_taxonomy_id from {$this->tables['term_relationships']} where object_id = '$post_id') as temp 
			on temp.term_taxonomy_id = {$this->tables['term_taxonomy']}.term_taxonomy_id
			where {$this->tables['terms']}.term_id = {$this->tables['term_taxonomy']}.term_id
			and {$this->tables['term_taxonomy']}.taxonomy = 'category'
			order by name asc";
		$result = $this->query($query);
		$cats = array();
		if ($result && mysql_num_rows( $result) > 0 ) {
			while ($row = mysql_fetch_assoc($result)) {
				if ( ( !$this->categories_to_include || in_array($row['slug'],$this->categories_to_include) ) && !in_array($row['slug'],$this->categories_to_exclude) ) {
					$cats[$row['term_id']] = $row;
				}
			}
		}
		return $cats;		
	}
	
	/**
	 * Returns an array with title and description for a particular image that has been uploaded into the gallery.
	 * 
	 * @return array|bool Array if successful, false if not.
	 * @param string $image_location The path to an image that has been uploaded.
	 * @author Samuel Parsons
	 **/
	public function get_image_description_credits($image_location) {
		$image_location = mysql_real_escape_string($image_location, $this->connection);
		$query = "SELECT ID, post_title, post_content FROM {$this->tables['posts']} WHERE guid LIKE '%$image_location' AND (post_title != '' OR post_content != '')";
		$result = $this->query($query);
		if ($result && mysql_num_rows($result) > 0 ) return mysql_fetch_assoc($result);
		else return false;
	}
	
	/**
	 * Returns all categories or those within the optional parent category id.
	 *
	 * @param int|bool (optional) parent category
	 * @return bool|array $categories Array
	 **/
	public function get_categories($parent_id=false) {
		if ($this->categories!=array()) { 		// if we have a cache, return that.
			return $this->categories;
		}
		
		// no cache, so grab from the database.
		if ($parent_id !== false) {
			$parent_id = mysql_real_escape_string(intval($parent_id),$this->connection);
			$parent_query = "{$this->tables['term_taxonomy']}.parent = '$parent_id' ";
		}
		else {
			$parent_query = "";
		}
		$get_query = "
		select * from {$this->tables['terms']}, {$this->tables['term_taxonomy']} 
			where {$this->tables['terms']}.term_id = {$this->tables['term_taxonomy']}.term_id
			and {$this->tables['term_taxonomy']}.taxonomy = 'category'
			$parent_query
			order by name asc ";
		$get_result = $this->query($get_query);
		if (!$get_result) {
			echo mysql_error($this->connection);
			return false;
		}
		while ($row = mysql_fetch_assoc($get_result) ) {
			if ( ( !$this->categories_to_include || in_array($row['slug'],$this->categories_to_include) ) && !in_array($row['slug'],$this->categories_to_exclude) ) {
				$this->categories[$row['term_id']] = $row;
				$this->categories_by_slug[$row['slug']] = $row;
				$this->categories_ids[] = $row['term_id']; 
				$this->categories_slugs[] = $row['slug']; 				
			}
		}
		$this->category_tree = $this->generate_category_tree();
		$this->get_children();
		return $this->categories;
	}
	
	/**
	 * Attempts to run a SQL query and returns a result.
	 * This is a wrapper for the mysql_query function that will keep track of query times, so that we can display some debugging info. 
	 * For internal class use only.
	 *
	 * @param int|bool (optional) parent category
	 * @return bool|array $categories Array
	 **/
	private function query($q) {
		$begin = microtime();
		list($begin_usec, $begin_sec) = explode(" ", $begin);
		$result = mysql_query($q,$this->connection);
		$end = microtime();	
		list($end_usec, $end_sec) = explode(" ", $end);	
		$q_time = ((float)$end_sec+$end_usec)-((float)$begin_sec+$begin_usec);
		$this->logs[] = array( 'begin'=> $begin, 'end'=>$end, 'query'=> $q, 'query_time'=>$q_time );
		$this->total_q_time += $q_time;
		return $result;
	}
	
	/**
	 * Build a category tree.
	 *
	 * @param int $parent (optional) The ID of category that we want to start at. If blank, then is starts at the top level.
	 * @return array $categories Array of tree of category ID's.
	 **/
	private function generate_category_tree($parent=0) {
		// go through and find categories with that parent recursively.
		$cats = array();
		foreach( $this->categories as $cat ) {
			if ($cat['parent']==$parent) {
				$cats[$cat['term_id']] = $this->generate_category_tree($cat['term_id']);
			}
		}
		return $cats;
	}

	/**
	 * Returns the children categories
	 *
	 * @param int $parent (optional) The ID of category that we much find children of.
	 * @return array $categories Array of tree of category ID's.
	 **/
	private function get_children() {
		$cats = array();
		foreach($this->categories_ids as $id) {
			$cats[$id] = array();
		}
		$parents = array();
		foreach($this->categories as $id => $cat) {
			if ($cat['parent'] != 0) {
				$parents[] = $cat['parent'];
				$cats[$cat['parent']][] = $id;
			}
		}
		$merges = 1; $count = 0;
		while( $merges >0 && $count < 12) {
//			echo $count;
			$merges = 0;
			foreach ($parents as $id) {
				$children = $cats[$id];
				foreach($children as $child) {
					$temp = array_diff($cats[$child],$children);
					if (count($temp) > 0) {
						$cats[$id] = array_merge($children,$temp);
						$merges++;
					}
				}
			}
			$count++;
		}
		ksort($cats);
		$this->categories_children = $cats;
		return $this->categories_children;
	}
	
	/**
	 * Sets up the tables on the initialization of the class. 
	 *
	 * @param bool|int $multiuser_blog_id The ID of a multisite blog to access. If false (default), then will build the list of tables needed for a non-multisite WP installation.
	 * @return void
	 **/
	private function setup_tables($multiuser_blog_id=false,$table_prefix="wp") {
		$table_prefix = preg_replace("/[^a-zA-Z0-9_-]/", "", $table_prefix);
		if ( $multiuser_blog_id ) 	$mu_prefix = "_".intval($multiuser_blog_id)."_";
		else 						$mu_prefix = "_";
		foreach($this->tables as $key => $val) {
			$this->tables[$key] = $table_prefix.$mu_prefix.$key;
		}
		foreach($this->tables_mu as $key => $val) {
			$this->tables_mu[$key] = $table_prefix."_".$key;
		}
	}
	
	/**
	 * Applies the WP formatting functions to a string.
	 *
	 * @param string $content The content to apply the WP formatting to. Usually this will be content of a post.
	 * @return string The content with the WP formatting functions applied.
	 **/
	private function format_content($content) {
		$content = wptexturize($content);
		$content = wpautop($content);
		// $content = wp_specialchars($content);
		return $content;
	}
	
	
	
	
}







?>