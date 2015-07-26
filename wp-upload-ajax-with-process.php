<?php
/* 
Plugin Name: Thạch Phạm Upload File
Plugin URI: http://hoidap.thachpham.com/
Description: Upload file ngoài frontend bằng ajax và process.
Author: Vũ Đức Nam
Version: 0.1
Author URI: http://vuducnam.com/
*/

define('TPU_FOLDER', basename(dirname(__FILE__)));
define('TPU_URL', plugin_dir_url(TPU_FOLDER).TPU_FOLDER.'/');
define('TPU_CSS', TPU_URL.'assets/css/');
define('TPU_JS', TPU_URL.'assets/js/');
/**
* 
*/
class TP_Upload_File
{
	
	function __construct()
	{
		/**
		* Đây là action thêm style vào header
		*/
		add_action( 'wp_enqueue_scripts', array($this, 'tp_enqueue_styles') );

		/**
		* Đây là action thêm script vào header vs footer
		*/
		add_action( 'wp_enqueue_scripts', array($this, 'tp_enqueue_scripts') );

		/**
		* Đây là action để thêm shortcode [tp_upload]
		*/
		add_shortcode('tp_upload', array($this, 'tp_shortcode'));

		/**
		* Đây là action đăng ký ajax upload
		*/
		add_action( 'wp_ajax_tp_upload', array($this, 'tp_upload') );
        add_action( 'wp_ajax_nopriv_tp_upload', array($this, 'tp_upload') );

        /**
		* Đây là action đăng ký ajax delete upload
		*/
		add_action( 'wp_ajax_tp_delete_upload', array($this, 'tp_delete_upload') );
        add_action( 'wp_ajax_nopriv_tp_delete_upload', array($this, 'tp_delete_upload') );
	}

	/**
	* Đây là function thêm style
	*/
	public function tp_enqueue_styles(){
		wp_enqueue_style( 'tp-style-icon', '//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css', array(), '0.1', 'all' );
		wp_enqueue_style( 'tp-style-uikit', TPU_CSS . 'uikit.docs.min.css', array(), '0.1', 'all' );
		wp_enqueue_style( 'tp-style-main', TPU_CSS . 'main.css', array(), '0.1', 'all' );
	}

	/**
	* Đây là function thêm script
	*/
	public function tp_enqueue_scripts(){
		wp_enqueue_script( 'tpu-script-uikit', TPU_JS . 'uikit.min.js', array( 'jquery' ), '0.1', false );
		wp_enqueue_script( 'tpu-script-upload', TPU_JS . 'upload.js', array( 'jquery' ), '0.1', false );
		wp_enqueue_script( 'tpu-script-main', TPU_JS . 'main.js', array( 'jquery' ), '0.1', true );
		wp_localize_script( 'tpu-script-main', 'tp_ajax_url', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
	}

	/**
	* Đây là function shortcode
	*/
	public function tp_shortcode(){
		ob_start();
			?>
				<div id="upload-drop" class="uk-placeholder uk-text-center">
		            <i class="fa fa-cloud-upload uk-icon-medium uk-text-muted uk-margin-small-right"></i> Attach binaries by dropping them here or <a class="uk-form-file">selecting one<input id="upload-select" name="uploadfile" type="file"></a>.
		            <div id="progressbar" class="uk-progress uk-hidden">
			            <div class="uk-progress-bar" style="width: 0%;">0%</div>
			        </div>
			        <div id="upload-results" style="display:none;">
				    	<div class="uk-alert">
				    		<ul id="files" class="uk-list"><li class="clearfix" style="clear: both;"></li></ul>
				    	</div>
				    </div>
		        </div>
		        <?php $upload_nonce = wp_create_nonce( 'tp_upload_nonce' );?>
		        <script>

				    (function($) {
						"use strict";
					    var progressbar = $("#progressbar"),
				            bar         = progressbar.find('.uk-progress-bar'),
				            settings    = {

				            action: "<?php echo esc_url( admin_url('admin-ajax.php'));?>?action=tp_upload&_wpnonce=<?php echo $upload_nonce; ?>", // ajax upload url
				            param: 'uploadfile',//gửi data input file để xử lý trong php
				            allow : "*.(jpg|png|jpeg|gif)",//file cho phép upload
				            type: 'json',//kiểu trả về - json, html...

				            loadstart: function() {
				                bar.css("width", "0%").text("0%");
				                progressbar.removeClass("uk-hidden");
				            },

				            progress: function(percent) {
				                percent = Math.ceil(percent);
				                bar.css("width", percent+"%").text(percent+"%");
				            },

				            allcomplete: function(data) {

				            	bar.css("width", "100%").text("100%");
					            setTimeout(function(){ progressbar.addClass("uk-hidden");}, 250);

					            $("#upload-results").show();
	
								if( data.status == "1" ){

									$('#files .clearfix').before('<li class="success"><img src="'+ data.url +'" alt="" data-name="'+ data.name_tmp +'" data-id="'+ data.id +'" style="max-width:35px;"/><br />'+ data.name_tmp +' <a href="" class="remove_img uk-alert-close uk-close" data-delete="'+ data.id +'"></a></li>');

									new tp_remove_data_upload("#files .success .remove_img");//hàm xóa file upload

								} else{
									
									$("#upload-results .uk-alert").addClass('uk-alert-danger');
									$('#files .clearfix').before('<li class="error">'+ data.message +'</li>');
									
								}

				            }
				        };

				        var select = UIkit.uploadSelect($("#upload-select"), settings),
				            drop   = UIkit.uploadDrop($("#upload-drop"), settings);
				            
				    })(jQuery);

				</script>
			<?php
		$out = ob_get_contents();
		ob_end_clean();
		return $out;
	}


	/**
	* Đây là function để chạy action upload file ajax
	*/
	public function tp_upload(){

		$status = array('status'=> '0', 'name'=> '', 'name_tmp' => '', 'url' => '', 'id' => '', 'message'=> 'No file sent.');

		if( isset($_FILES['uploadfile']['name']) && !empty($_FILES['uploadfile']['name']) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'tp_upload_nonce' ) ){

			$mimes = array( 'image/jpeg', 'image/png', 'image/gif', 'image/jpg' );

			$attach_id = false;

			$file_name = basename( $_FILES['uploadfile']['name'] );
			
			$file_type = wp_check_filetype( $file_name );
			
			$file_renamed = mt_rand( 1000, 1000000 ) . '.' . $file_type['ext'];
			
			$upload = array(
				'name' => $file_renamed,
				'type' => $file_type['type'],
				'tmp_name' => $_FILES['uploadfile']['tmp_name'],
				'error' => $_FILES['uploadfile']['error'],
				'size' => $_FILES['uploadfile']['size']
			);

			if( in_array( $file_type['type'], $mimes) ){

				$file = wp_handle_upload( $upload, array( 'test_form' => false ) );

				if ( $file && !isset( $file['error'] ) ) {
					
					$status['status'] = '1';
					$status['name'] = $file_renamed;
					$status['name_tmp'] = $_FILES['uploadfile']['name'];
					$status['url'] = esc_url($file['url']);
					$status['message'] = 'File is uploaded successfully.';

					if ( isset( $file['file'] ) ) {

						$file_loc = $file['file'];

						$attachment = array(
							'post_mime_type' => $file_type['type'],
							'post_title' => preg_replace( '/\.[^.]+$/', '', basename( $file_name ) ),
							'post_content' => '',
							'post_status' => 'inherit',
							'guid' => esc_url($file['url'])
						);

						$post_id = 0;
						$attach_id = wp_insert_attachment( $attachment, $file_loc, $post_id );
						$attach_data = wp_generate_attachment_metadata( $attach_id, $file_loc );
						wp_update_attachment_metadata( $attach_id, $attach_data );
					}

					if ( $attach_id ) {

						$status['id'] = $attach_id;

					}

				}else{

					$status['message'] = $file['error'];
				}

			}else{
			
				$status['message'] = 'Invalid file format.';
			}

		}

		echo json_encode($status);
		die();
	}

	/**
	* Đây là function để chạy action delete file ajax
	*/
	public function tp_delete_upload(){

		$status = array('status'=> '0');

		if( isset($_REQUEST['attach_id']) && !empty($_REQUEST['attach_id']) ){

			wp_delete_attachment( $_REQUEST['attach_id'] );
		}

		echo json_encode($status);
		die();
	}

}

new TP_Upload_File();

?>