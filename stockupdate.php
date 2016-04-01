<?php
/**
 * Plugin Name: Stock Update
 * Plugin URI: http://facebook.com/Alamdeveloper
 * Description: Update Products Stock By CSV
 * Version: 1.0.0
 * Author: Mohd Alam
 * Author URI: http://facebook.com/Alamdeveloper
 * License: IPL
 */



// ADDING MENU IN ADMIN FOR STOCK UPDATE
/** Step 2 (from text above). */
add_action( 'admin_menu', 'my_plugin_menu' );

/** Step 1. */
function my_plugin_menu() {
    add_submenu_page( 'woocommerce','STOCK UPDATE', 'STOCK UPDATE', 'manage_options', 'updateProductStock', 'updateProductStock' );
}

/** Step 3. */
function updateProductStock() {
	
	
	// UPDATE STOCK
    if($_REQUEST['action']=="updateProductStockPost" && isset($_REQUEST['action'])){
        updateProductStockProcess();
    }else{
	

        if ( !current_user_can( 'manage_options' ) )  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        $html_form = "
        	<div style='width: 90%;margin: 10% 10% 0% 6%;border:1px solid #D4D3D3;background:white;'>
        		<div style='    text-align: center;  font-size: 24px;  padding: 35px;'>
        				Update Product Stock By CSV
        		</div>
        		<hr/>
        		
        ";
        if(isset($_REQUEST['error']) && !empty($_REQUEST['error'])){
        	$html_form.="<div style='color:red;text-align:center;'>".$_REQUEST['error']."</div>";
        }else if(isset($_REQUEST['success']) && !empty($_REQUEST['success'])){
        	$html_form.="<div style='color:green;text-align:center;'>Csv File Executed Successfully</div>";
        }

        $html_form.="		
	        	<div style='padding: 35px;'>
		        	<form action ='".get_site_url()."/wp-admin/admin.php?page=updateProductStock&action=updateProductStockPost' method='post' enctype='multipart/form-data'>
		        		Select Csv File <input type='file' name='stock_update_file' />
		        		<input type='submit' name='stock_update' value='Submit'/>
		        	</form>
	        	</div>
	        	<hr/>
	        	<div style='padding:5px;'>update product stock</div>
	        	
	        	<div style='padding:5px;float:right'>
	        		<a href='".get_site_url()."/wp-admin/admin.php?page=stock-manager-import-export&action=export'>Download Sample CSV</a>
	        	</div>
	        <div>	
	        ";

        echo $html_form;
    }
}

function updateProductStockProcess(){
	global $wpdb;
	if(isset($_FILES['stock_update_file']['name']) && !empty($_FILES['stock_update_file']['name']) ){
		
		$csv_type = array(
            'text/csv',
            'application/csv',
            'application/vnd.ms-excel',
            'text/plain',
            'text/tsv'
        );

		if(in_array($_FILES['stock_update_file']['type'], $csv_type)){
		    $file = $_FILES['stock_update_file']['tmp_name'];
		
			

		    if (($handle = fopen($file, "r")) !== FALSE) {
		        $incr_data_index=0;
		        $data_query =array();
		        $i=0;
		        $v=0;
		        $no_of_queries=0;
		        $line = 0;
		        $invalid_data_array="";
		        $valid_data_array = "";
		        while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
		        	
		        	if(count($data)>2){
		        		$error = "<h1 style='color:red'>Error</h1>    ";
		        		$error .= "More Than 2 index found in csv";
		        		echo $error;
		        		die;
						//wp_redirect( get_site_url().'/wp-admin/admin.php?page=updateProductStock&error='.$error);
		        	}else{

			            if($i !=0){
			                // check request id in database and status
			                $product_id = @$data[0];
			                $stock = @$data[1]; 

			                //if(empty($product_id) || (!is_int($product_id)) || (empty($stock)==true) || (is_int($stock)==false) || $stock <=0 ){
			                if(empty($product_id) || (!ctype_digit($product_id)) || !ctype_digit($stock) || $stock <=0 ){
			                	$invalid_data_array[] = "Invalid Product id $product_id or Invalid $stock quantity at line $line";

			                }else{
			                	//$data_query[] =  array('product_id'=>$product_id,'stock'=>$stock);

			                	//$wpdb->update('wp_postmeta', array('meta_value'=>$stock), array('post_id'=>$product_id,'meta_key'=>'_stock'));		
			                	//$wpdb->update('wp_postmeta', array('meta_value'=>'instock'), array('post_id'=>$product_id,'meta_key'=>'_stock_status'));		
			                	$valid_data_array [] = array($product_id,$stock);
			                }	
			            }
			        }    
			       $i++;
			       $line++;
		        }
		        
		        fclose($handle);


		        // update product quantity in database
		        updateProductStockData($valid_data_array);

		        // show error if any invalid product id or quantity found invalid
		        if(!empty($invalid_data_array)){
		        	foreach ($invalid_data_array as $value) {
		        		echo "<p style='color:red;'>".$value."</p><br/>";
		        	}
		        }

		       
		        $error = "Csv File Executed Successfully";
		        echo $error;
				//wp_redirect( get_site_url().'/wp-admin/admin.php?page=updateProductStock&success=true');
		   }else{
		   	$error = "Could Not Open CSV File";
			wp_redirect( get_site_url().'/wp-admin/admin.php?page=updateProductStock&error='.$error);
		   }
		}else{
			$error = "Please Select Valid Csv File";
			wp_redirect( get_site_url().'/wp-admin/admin.php?page=updateProductStock&error='.$error);
		}
    }else{
    	$error = "Please Select Valid Csv File";
    	wp_redirect( get_site_url().'/wp-admin/admin.php?page=updateProductStock&error='.$error);
    }   
}



function updateProductStockData($data){
	global $wpdb;

	$postids=  array();

	// updating quantity
	if(!empty($data)){
		$query_stock = "UPDATE wp_postmeta SET meta_value = CASE post_id ";
		$query_stock_status = "UPDATE wp_postmeta SET meta_value = CASE post_id ";

		for($i=0;$i<count($data);$i++){
			$postids [] = $data[$i][0];
			$quantity_stock.=" WHEN ".$data[$i][0]." THEN ".$data[$i][1];
			$quantity_stock_status.=" WHEN ".$data[$i][0]." THEN 'instock'";
		}	
		$q1 .=$quantity_stock." END ";
		$q2 .=$quantity_stock_status." END ";


		$query_stock_ = "where post_id in (".implode(',',$postids).") and meta_key='_stock'";
		
		
		if($wpdb->query($wpdb->prepare($query_stock.$q1.$query_stock_))){

		}else{
			echo mysql_error();
		}

		

		// updating stock status
		$query_stock_status_ = "where post_id in (".implode(',',$postids).") and meta_key='_stock_status'";
		
		if($wpdb->query($wpdb->prepare($query_stock_status.$q2.$query_stock_status_))){

		}else{

		}
	}   
}
?>