<?php
	$domain = 'zoto.pl'; // twisted zoto domain
	$domain2 = 'http://www.notice.zoto.pl:81'; // uploader domain
	#$domain = split("[.:]", $_SERVER['HTTP_HOST']);
	#$domain = ($domain[1] . "." . $domain[2]);
	$color = $_COOKIE['zoto_color'];
	if(!$color){
		$color = "white_blue";
	}
	$auth = $_COOKIE['auth_hash'];
	$rnd = rand();
	$mochijs = 'http://www.'.$domain.'/js/'.$rnd.'/third_party/MochiKit/packed/MochiKit.js';
	$zotojs = 'http://www.'.$domain.'/js/'.$rnd.'/zoto.js';
	$csslayout = 'http://www.'.$domain.'/css/'.$rnd.'/zoto_layout.css';
	$cssfont = 'http://www.'.$domain.'/css/'.$rnd.'/zoto_font.css';
	$csscolor = 'http://www.'.$domain.'/css/'.$rnd.'/zoto_'.$color.'.css';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>uploader</title>
<link href="<?php echo($csslayout); ?>" type="text/css" rel="stylesheet" />
<link href="<?php echo($cssfont); ?>" type="text/css" rel="stylesheet" />
<link href="<?php echo($csscolor); ?>" type="text/css" rel="stylesheet" />
<script type="text/javascript" src="<?php echo($mochijs); ?>"></script>

<style type="text/css">@import url(<?=$domain2?>/plupload/css/plupload.queue.css);</style>
<script type="text/javascript" src="http://www.google.com/jsapi"></script>
<script type="text/javascript">
	google.load("jquery", "1.3");
</script>

<!-- Thirdparty intialization scripts, needed for the Google Gears and BrowserPlus runtimes -->
<script type="text/javascript" src="<?=$domain2?>/plupload/js/gears_init.js"></script>
<script type="text/javascript" src="http://bp.yahooapis.com/2.4.21/browserplus-min.js"></script>

<!-- Load plupload and all it's runtimes and finally the jQuery queue widget -->
<script type="text/javascript" src="<?=$domain2?>/plupload/js/plupload.full.min.js"></script>
<script type="text/javascript" src="<?=$domain2?>/plupload/js/jquery.plupload.queue.min.js"></script>


<script type="text/javascript">

function _(str){
	return str;
}

/**
	zoto_html_upload_form
	A form that allows users to upload files by posting to an iframe.
	@constructor
*/
function zoto_html_upload_form(){
	this.el = DIV({'class':'iframe_uploader'});
	this.__init = false;
}
zoto_html_upload_form.prototype = {
	/**
		build
		Builds the DOM for the form. This form should be loaded via an IFrame so xss issues are avoided.
	*/
	build:function(){
		if(!this.__init){
			this.__init = true;

			//Iframe
			this.iframe = createDOM('iframe',{'name':'upload_frame', 'height':'1', 'width':'1', 'border':'0', 'frameBorder':'0','src':'about:blank'});
			connect(this.iframe, 'onload', this, 'iframe_loaded');
			
		};
	},
	/**
		handle_select
		Callback for the onselect event of the file input tag.
		Sets the posting flag and submits the form. Hides the form element and shows the spinner.
	*/
	handle_select:function(){
		this.posting = true;
		this.upload_form.submit();
		addElementClass(this.upload_form, 'invisible');
		removeElementClass(this.spinner, 'invisible');		
	},
	/**
		iframe_loaded
		Callback for the iframe's onload event. Checks to see if we had a successful upload or not. 
	*/
	iframe_loaded:function(){
		if(this.posting){
			var success = this.iframe.contentWindow.success || this.iframe.contentDocument.success || null;
			if(success){
				this.handle_success(success);
			} else {
				this.handle_success([-1,{'msg':'TShere was an error on the server'}])
			};
		};
	},

	/**
		handle_results
		Handles the results of the uplaod.
		@param {Boolean} success: The boolean results from the upload.php loaded in the iframe.
			if true, the upload succeeded.  If false the upload failed for some reason.
	*/
	handle_success:function(success){
		this.posting = false;
		addElementClass(this.spinner, 'invisible');
		removeElementClass(this.upload_form, 'invisible');	
		this.file_input.value = '';
		this.iframe.src = 'about:blank';
		if(success[0] == 0){
			replaceChildNodes(this.div_result, success[1].filename + ' uploaded successfully.  Upload another file if you like.');
		} else {
			replaceChildNodes(this.div_result, 
				_('The file '), success[1].filename ,_(' did not upload successfully,'),BR(),
				success[1].msg,
				'.', BR(), _(' Would you like to try again?')
			);
		};
	}
}
var upload_form = null;
connect(currentWindow(), 'onload', this, function(){
	var el = document.getElementById('html_upload_shell');
	upload_form = new zoto_html_upload_form();
	appendChildNodes(el, upload_form.el)
	upload_form.build();
});

// Convert divs to queue widgets when the DOM is ready
$().ready(function() {
	$("#uploader").pluploadQueue({
		// General settings
		runtimes : 'gears,flash,silverlight,browserplus,html5',
		url : 'multi_upload.php',
		max_file_size : '10mb',
		chunk_size : '1mb',
		unique_names : true,

		// Resize images on clientside if we can
		//resize : {width : 320, height : 240, quality : 90},
		//resize : false,

		// Specify what files to browse for
		filters : [
			{title : "Image files", extensions : "jpg,gif,png"},
		],

		// Flash settings
		flash_swf_url : '/plupload/js/plupload.flash.swf',

		// Silverlight settings
		silverlight_xap_url : '/plupload/js/plupload.silverlight.xap'
	});

	// Client side form validation
	$('form').submit(function(e) {
		var uploader = $('#uploader').pluploadQueue();

		// Validate number of uploaded files
		if (uploader.total.uploaded == 0) {
			alert('You must at least upload one file.');
			e.preventDefault();
		}
	});
});

</script>
</head>

<body>
	<div id="html_upload_shell"></div>

	<form method="post" action="upload_done.php">
		<div id="uploader">
			<p>You browser doesn't have Flash, Silverlight, Gears, BrowserPlus or HTML5 support.</p>
		</div>

				<br style="clear: both" />

				<input type="submit" value="Send" />

	</form>

</body>
</html>
