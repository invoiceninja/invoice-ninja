<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:cn="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2" xmlns:in="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:u="utils" version="2.0" exclude-result-prefixes="cac cbc u cn in xs">
	<xsl:output method="html" version="5.0" encoding="UTF-8" indent="no"/>
	<xsl:param name="stylesheet_url" select="'NONE'"/>
	<xsl:template name="doc-head">
		<meta charset="utf-8"/>
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
		<xsl:choose>
			<xsl:when test="$stylesheet_url = 'NONE'">
				<style>/*! normalize.css v3.0.3 | MIT License | github.com/necolas/normalize.css */html{font-family:sans-serif;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%}body{margin:0}article,aside,details,figcaption,figure,footer,header,hgroup,main,menu,nav,section,summary{display:block}audio,canvas,progress,video{display:inline-block;vertical-align:baseline}audio:not([controls]){display:none;height:0}[hidden],template{display:none}a{background-color:transparent}a:active,a:hover{outline:0}abbr[title]{border-bottom:1px dotted}b,strong{font-weight:bold}dfn{font-style:italic}h1{font-size:2em;margin:0.67em 0}mark{background:#ff0;color:#000}small{font-size:80%}sub,sup{font-size:75%;line-height:0;position:relative;vertical-align:baseline}sup{top:-0.5em}sub{bottom:-0.25em}img{border:0}svg:not(:root){overflow:hidden}figure{margin:1em 40px}hr{box-sizing:content-box;height:0}pre{overflow:auto}code,kbd,pre,samp{font-family:monospace, monospace;font-size:1em}button,input,optgroup,select,textarea{color:inherit;font:inherit;margin:0}button{overflow:visible}button,select{text-transform:none}button,html input[type="button"],input[type="reset"],input[type="submit"]{-webkit-appearance:button;cursor:pointer}button[disabled],html input[disabled]{cursor:default}button::-moz-focus-inner,input::-moz-focus-inner{border:0;padding:0}input{line-height:normal}input[type="checkbox"],input[type="radio"]{box-sizing:border-box;padding:0}input[type="number"]::-webkit-inner-spin-button,input[type="number"]::-webkit-outer-spin-button{height:auto}input[type="search"]{-webkit-appearance:textfield;box-sizing:content-box}input[type="search"]::-webkit-search-cancel-button,input[type="search"]::-webkit-search-decoration{-webkit-appearance:none}fieldset{border:1px solid #c0c0c0;margin:0 2px;padding:0.35em 0.625em 0.75em}legend{border:0;padding:0}textarea{overflow:auto}optgroup{font-weight:bold}table{border-collapse:collapse;border-spacing:0}td,th{padding:0}/*! Source: https://github.com/h5bp/html5-boilerplate/blob/master/src/css/main.css */@media print{*,*:before,*:after{background:transparent !important;color:#000 !important;box-shadow:none !important;text-shadow:none !important}a,a:visited{text-decoration:underline}a[href]:after{content:" (" attr(href) ")"}abbr[title]:after{content:" (" attr(title) ")"}a[href^="#"]:after,a[href^="javascript:"]:after{content:""}pre,blockquote{border:1px solid #999;page-break-inside:avoid}thead{display:table-header-group}tr,img{page-break-inside:avoid}img{max-width:100% !important}p,h2,h3{orphans:3;widows:3}h2,h3{page-break-after:avoid}.navbar{display:none}.btn&gt;.caret,.dropup&gt;.btn&gt;.caret{border-top-color:#000 !important}.label{border:1px solid #000}.table,#tax table{border-collapse:collapse !important}.table td,#tax table td,.table th,#tax table th{background-color:#fff !important}.table-bordered th,.table-bordered td{border:1px solid #ddd !important}}*{-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}*:before,*:after{-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}html{font-size:10px;-webkit-tap-highlight-color:transparent}body{font-family:"Helvetica Neue",Helvetica,Arial,sans-serif;font-size:14px;line-height:1.42857;color:#333;background-color:#eee}input,button,select,textarea{font-family:inherit;font-size:inherit;line-height:inherit}a{color:#337ab7;text-decoration:none}a:hover,a:focus{color:#23527c;text-decoration:underline}a:focus{outline:5px auto -webkit-focus-ring-color;outline-offset:-2px}figure{margin:0}img{vertical-align:middle}.img-responsive{display:block;max-width:100%;height:auto}.img-rounded{border-radius:6px}.img-thumbnail{padding:4px;line-height:1.42857;background-color:#eee;border:1px solid #ddd;border-radius:4px;-webkit-transition:all 0.2s ease-in-out;-o-transition:all 0.2s ease-in-out;transition:all 0.2s ease-in-out;display:inline-block;max-width:100%;height:auto}.img-circle{border-radius:50%}hr{margin-top:20px;margin-bottom:20px;border:0;border-top:1px solid #eee}.sr-only{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0, 0, 0, 0);border:0}.sr-only-focusable:active,.sr-only-focusable:focus{position:static;width:auto;height:auto;margin:0;overflow:visible;clip:auto}[role="button"]{cursor:pointer}h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6{font-family:inherit;font-weight:500;line-height:1.1;color:inherit}h1 small,h1 .small,h1 dt,h1 #footer,h1 #tax table th,#tax table h1 th,h2 small,h2 .small,h2 dt,h2 #footer,h2 #tax table th,#tax table h2 th,h3 small,h3 .small,h3 dt,h3 #footer,h3 #tax table th,#tax table h3 th,h4 small,h4 .small,h4 dt,h4 #footer,h4 #tax table th,#tax table h4 th,h5 small,h5 .small,h5 dt,h5 #footer,h5 #tax table th,#tax table h5 th,h6 small,h6 .small,h6 dt,h6 #footer,h6 #tax table th,#tax table h6 th,.h1 small,.h1 .small,.h1 dt,.h1 #footer,.h1 #tax table th,#tax table .h1 th,.h2 small,.h2 .small,.h2 dt,.h2 #footer,.h2 #tax table th,#tax table .h2 th,.h3 small,.h3 .small,.h3 dt,.h3 #footer,.h3 #tax table th,#tax table .h3 th,.h4 small,.h4 .small,.h4 dt,.h4 #footer,.h4 #tax table th,#tax table .h4 th,.h5 small,.h5 .small,.h5 dt,.h5 #footer,.h5 #tax table th,#tax table .h5 th,.h6 small,.h6 .small,.h6 dt,.h6 #footer,.h6 #tax table th,#tax table .h6 th{font-weight:normal;line-height:1;color:#777}h1,.h1,h2,.h2,h3,.h3{margin-top:20px;margin-bottom:10px}h1 small,h1 .small,h1 dt,h1 #footer,h1 #tax table th,#tax table h1 th,.h1 small,.h1 .small,.h1 dt,.h1 #footer,.h1 #tax table th,#tax table .h1 th,h2 small,h2 .small,h2 dt,h2 #footer,h2 #tax table th,#tax table h2 th,.h2 small,.h2 .small,.h2 dt,.h2 #footer,.h2 #tax table th,#tax table .h2 th,h3 small,h3 .small,h3 dt,h3 #footer,h3 #tax table th,#tax table h3 th,.h3 small,.h3 .small,.h3 dt,.h3 #footer,.h3 #tax table th,#tax table .h3 th{font-size:65%}h4,.h4,h5,.h5,h6,.h6{margin-top:10px;margin-bottom:10px}h4 small,h4 .small,h4 dt,h4 #footer,h4 #tax table th,#tax table h4 th,.h4 small,.h4 .small,.h4 dt,.h4 #footer,.h4 #tax table th,#tax table .h4 th,h5 small,h5 .small,h5 dt,h5 #footer,h5 #tax table th,#tax table h5 th,.h5 small,.h5 .small,.h5 dt,.h5 #footer,.h5 #tax table th,#tax table .h5 th,h6 small,h6 .small,h6 dt,h6 #footer,h6 #tax table th,#tax table h6 th,.h6 small,.h6 .small,.h6 dt,.h6 #footer,.h6 #tax table th,#tax table .h6 th{font-size:75%}h1,.h1{font-size:36px}h2,.h2{font-size:30px}h3,.h3{font-size:24px}h4,.h4{font-size:18px}h5,.h5{font-size:14px}h6,.h6{font-size:12px}p{margin:0 0 10px}.lead{margin-bottom:20px;font-size:16px;font-weight:300;line-height:1.4}@media (min-width: 535px){.lead{font-size:21px}}small,.small,dt,#footer,#tax table th{font-size:85%}mark,.mark{background-color:#fcf8e3;padding:.2em}.text-left{text-align:left}.text-right,#totals dl dd{text-align:right}.text-center,.line .number{text-align:center}.text-justify{text-align:justify}.text-nowrap{white-space:nowrap}.text-lowercase{text-transform:lowercase}.text-uppercase,.initialism{text-transform:uppercase}.text-capitalize{text-transform:capitalize}.text-muted{color:#777}.text-primary{color:#337ab7}a.text-primary:hover,a.text-primary:focus{color:#286090}.text-success{color:#3c763d}a.text-success:hover,a.text-success:focus{color:#2b542c}.text-info{color:#31708f}a.text-info:hover,a.text-info:focus{color:#245269}.text-warning{color:#8a6d3b}a.text-warning:hover,a.text-warning:focus{color:#66512c}.text-danger{color:#a94442}a.text-danger:hover,a.text-danger:focus{color:#843534}.bg-primary{color:#fff}.bg-primary{background-color:#337ab7}a.bg-primary:hover,a.bg-primary:focus{background-color:#286090}.bg-success{background-color:#dff0d8}a.bg-success:hover,a.bg-success:focus{background-color:#c1e2b3}.bg-info{background-color:#d9edf7}a.bg-info:hover,a.bg-info:focus{background-color:#afd9ee}.bg-warning{background-color:#fcf8e3}a.bg-warning:hover,a.bg-warning:focus{background-color:#f7ecb5}.bg-danger{background-color:#f2dede}a.bg-danger:hover,a.bg-danger:focus{background-color:#e4b9b9}.page-header{padding-bottom:9px;margin:40px 0 20px;border-bottom:1px solid #eee}ul,ol{margin-top:0;margin-bottom:10px}ul ul,ul ol,ol ul,ol ol{margin-bottom:0}.list-unstyled{padding-left:0;list-style:none}.list-inline{padding-left:0;list-style:none;margin-left:-5px}.list-inline&gt;li{display:inline-block;padding-left:5px;padding-right:5px}dl{margin-top:0;margin-bottom:20px}dt,dd{line-height:1.42857}dt{font-weight:bold}dd{margin-left:0}.dl-horizontal dd:before,.dl-horizontal dd:after{content:" ";display:table}.dl-horizontal dd:after{clear:both}@media (min-width: 535px){.dl-horizontal dt{float:left;width:160px;clear:left;text-align:right;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.dl-horizontal dd{margin-left:180px}}abbr[title],abbr[data-original-title]{cursor:help;border-bottom:1px dotted #777}.initialism{font-size:90%}blockquote{padding:10px 20px;margin:0 0 20px;font-size:17.5px;border-left:5px solid #eee}blockquote p:last-child,blockquote ul:last-child,blockquote ol:last-child{margin-bottom:0}blockquote footer,blockquote small,blockquote .small,blockquote dt,blockquote #footer,blockquote #tax table th,#tax table blockquote th{display:block;font-size:80%;line-height:1.42857;color:#777}blockquote footer:before,blockquote small:before,blockquote .small:before,blockquote dt:before,blockquote #footer:before,blockquote #tax table th:before,#tax table blockquote th:before{content:'\2014 \00A0'}.blockquote-reverse,blockquote.pull-right{padding-right:15px;padding-left:0;border-right:5px solid #eee;border-left:0;text-align:right}.blockquote-reverse footer:before,.blockquote-reverse small:before,.blockquote-reverse .small:before,.blockquote-reverse dt:before,.blockquote-reverse #footer:before,.blockquote-reverse #tax table th:before,#tax table .blockquote-reverse th:before,blockquote.pull-right footer:before,blockquote.pull-right small:before,blockquote.pull-right .small:before,blockquote.pull-right dt:before,blockquote.pull-right #footer:before,blockquote.pull-right #tax table th:before,#tax table blockquote.pull-right th:before{content:''}.blockquote-reverse footer:after,.blockquote-reverse small:after,.blockquote-reverse .small:after,.blockquote-reverse dt:after,.blockquote-reverse #footer:after,.blockquote-reverse #tax table th:after,#tax table .blockquote-reverse th:after,blockquote.pull-right footer:after,blockquote.pull-right small:after,blockquote.pull-right .small:after,blockquote.pull-right dt:after,blockquote.pull-right #footer:after,blockquote.pull-right #tax table th:after,#tax table blockquote.pull-right th:after{content:'\00A0 \2014'}address{margin-bottom:20px;font-style:normal;line-height:1.42857}.container,#document{margin-right:auto;margin-left:auto;padding-left:15px;padding-right:15px}.container:before,#document:before,.container:after,#document:after{content:" ";display:table}.container:after,#document:after{clear:both}@media (min-width: 535px){.container,#document{width:750px}}@media (min-width: 992px){.container,#document{width:970px}}@media (min-width: 1200px){.container,#document{width:1170px}}.container-fluid{margin-right:auto;margin-left:auto;padding-left:15px;padding-right:15px}.container-fluid:before,.container-fluid:after{content:" ";display:table}.container-fluid:after{clear:both}.row,div.line,#parties dl,#metadata dl,#delivery dl,#payment dl,#totals dl,#tax dl,.line,.line .details dl,.line .info dl{margin-left:-15px;margin-right:-15px}.row:before,#parties dl:before,#metadata dl:before,#delivery dl:before,#payment dl:before,#totals dl:before,#tax dl:before,.line:before,.line .details dl:before,.line .info dl:before,.row:after,#parties dl:after,#metadata dl:after,#delivery dl:after,#payment dl:after,#totals dl:after,#tax dl:after,.line:after,.line .details dl:after,.line .info dl:after{content:" ";display:table}.row:after,#parties dl:after,#metadata dl:after,#delivery dl:after,#payment dl:after,#totals dl:after,#tax dl:after,.line:after,.line .details dl:after,.line .info dl:after{clear:both}.col-xs-1,.col-sm-1,.line .number,.col-md-1,.col-lg-1,.col-xs-2,.col-sm-2,.col-md-2,.col-lg-2,.col-xs-3,.col-sm-3,.col-md-3,.col-lg-3,.col-xs-4,.col-sm-4,#metadata dl dt,.col-md-4,.col-lg-4,.col-xs-5,#parties dl dt,#metadata dl dt,#delivery dl dt,#payment dl dt,#totals dl dt,#tax dl dt,.line .details dl dt,.line .info dl dt,.col-sm-5,.col-md-5,#metadata dl dt,.col-lg-5,.col-xs-6,.col-sm-6,.col-md-6,.col-lg-6,.col-xs-7,#parties dl dd,#metadata dl dd,#delivery dl dd,#payment dl dd,#totals dl dd,#tax dl dd,.line .details dl dd,.line .info dl dd,.col-sm-7,.col-md-7,#metadata dl dd,.col-lg-7,.col-xs-8,.col-sm-8,#metadata dl dd,.col-md-8,.col-lg-8,.col-xs-9,.col-sm-9,.col-md-9,.col-lg-9,.col-xs-10,.col-sm-10,.col-md-10,.col-lg-10,.col-xs-11,.col-sm-11,.col-md-11,.col-lg-11,.col-xs-12,.col-sm-12,.col-md-12,.col-lg-12{position:relative;min-height:1px;padding-left:15px;padding-right:15px}.col-xs-1,.col-xs-2,.col-xs-3,.col-xs-4,.col-xs-5,#parties dl dt,#metadata dl dt,#delivery dl dt,#payment dl dt,#totals dl dt,#tax dl dt,.line .details dl dt,.line .info dl dt,.col-xs-6,.col-xs-7,#parties dl dd,#metadata dl dd,#delivery dl dd,#payment dl dd,#totals dl dd,#tax dl dd,.line .details dl dd,.line .info dl dd,.col-xs-8,.col-xs-9,.col-xs-10,.col-xs-11,.col-xs-12{float:left}.col-xs-1{width:8.33333%}.col-xs-2{width:16.66667%}.col-xs-3{width:25%}.col-xs-4{width:33.33333%}.col-xs-5,#parties dl dt,#metadata dl dt,#delivery dl dt,#payment dl dt,#totals dl dt,#tax dl dt,.line .details dl dt,.line .info dl dt{width:41.66667%}.col-xs-6{width:50%}.col-xs-7,#parties dl dd,#metadata dl dd,#delivery dl dd,#payment dl dd,#totals dl dd,#tax dl dd,.line .details dl dd,.line .info dl dd{width:58.33333%}.col-xs-8{width:66.66667%}.col-xs-9{width:75%}.col-xs-10{width:83.33333%}.col-xs-11{width:91.66667%}.col-xs-12{width:100%}.col-xs-pull-0{right:auto}.col-xs-pull-1{right:8.33333%}.col-xs-pull-2{right:16.66667%}.col-xs-pull-3{right:25%}.col-xs-pull-4{right:33.33333%}.col-xs-pull-5{right:41.66667%}.col-xs-pull-6{right:50%}.col-xs-pull-7{right:58.33333%}.col-xs-pull-8{right:66.66667%}.col-xs-pull-9{right:75%}.col-xs-pull-10{right:83.33333%}.col-xs-pull-11{right:91.66667%}.col-xs-pull-12{right:100%}.col-xs-push-0{left:auto}.col-xs-push-1{left:8.33333%}.col-xs-push-2{left:16.66667%}.col-xs-push-3{left:25%}.col-xs-push-4{left:33.33333%}.col-xs-push-5{left:41.66667%}.col-xs-push-6{left:50%}.col-xs-push-7{left:58.33333%}.col-xs-push-8{left:66.66667%}.col-xs-push-9{left:75%}.col-xs-push-10{left:83.33333%}.col-xs-push-11{left:91.66667%}.col-xs-push-12{left:100%}.col-xs-offset-0{margin-left:0%}.col-xs-offset-1{margin-left:8.33333%}.col-xs-offset-2{margin-left:16.66667%}.col-xs-offset-3{margin-left:25%}.col-xs-offset-4{margin-left:33.33333%}.col-xs-offset-5{margin-left:41.66667%}.col-xs-offset-6{margin-left:50%}.col-xs-offset-7{margin-left:58.33333%}.col-xs-offset-8{margin-left:66.66667%}.col-xs-offset-9{margin-left:75%}.col-xs-offset-10{margin-left:83.33333%}.col-xs-offset-11{margin-left:91.66667%}.col-xs-offset-12{margin-left:100%}@media (min-width: 535px){.col-sm-1,.line .number,.col-sm-2,.col-sm-3,.col-sm-4,#metadata dl dt,.col-sm-5,.col-sm-6,.col-sm-7,.col-sm-8,#metadata dl dd,.col-sm-9,.col-sm-10,.col-sm-11,.col-sm-12{float:left}.col-sm-1,.line .number{width:8.33333%}.col-sm-2{width:16.66667%}.col-sm-3{width:25%}.col-sm-4,#metadata dl dt{width:33.33333%}.col-sm-5{width:41.66667%}.col-sm-6{width:50%}.col-sm-7{width:58.33333%}.col-sm-8,#metadata dl dd{width:66.66667%}.col-sm-9{width:75%}.col-sm-10{width:83.33333%}.col-sm-11{width:91.66667%}.col-sm-12{width:100%}.col-sm-pull-0{right:auto}.col-sm-pull-1{right:8.33333%}.col-sm-pull-2{right:16.66667%}.col-sm-pull-3{right:25%}.col-sm-pull-4{right:33.33333%}.col-sm-pull-5{right:41.66667%}.col-sm-pull-6{right:50%}.col-sm-pull-7{right:58.33333%}.col-sm-pull-8{right:66.66667%}.col-sm-pull-9{right:75%}.col-sm-pull-10{right:83.33333%}.col-sm-pull-11{right:91.66667%}.col-sm-pull-12{right:100%}.col-sm-push-0{left:auto}.col-sm-push-1{left:8.33333%}.col-sm-push-2{left:16.66667%}.col-sm-push-3{left:25%}.col-sm-push-4{left:33.33333%}.col-sm-push-5{left:41.66667%}.col-sm-push-6{left:50%}.col-sm-push-7{left:58.33333%}.col-sm-push-8{left:66.66667%}.col-sm-push-9{left:75%}.col-sm-push-10{left:83.33333%}.col-sm-push-11{left:91.66667%}.col-sm-push-12{left:100%}.col-sm-offset-0{margin-left:0%}.col-sm-offset-1{margin-left:8.33333%}.col-sm-offset-2{margin-left:16.66667%}.col-sm-offset-3{margin-left:25%}.col-sm-offset-4{margin-left:33.33333%}.col-sm-offset-5{margin-left:41.66667%}.col-sm-offset-6{margin-left:50%}.col-sm-offset-7{margin-left:58.33333%}.col-sm-offset-8{margin-left:66.66667%}.col-sm-offset-9{margin-left:75%}.col-sm-offset-10{margin-left:83.33333%}.col-sm-offset-11{margin-left:91.66667%}.col-sm-offset-12{margin-left:100%}}@media (min-width: 992px){.col-md-1,.col-md-2,.col-md-3,.col-md-4,.col-md-5,#metadata dl dt,.col-md-6,.col-md-7,#metadata dl dd,.col-md-8,.col-md-9,.col-md-10,.col-md-11,.col-md-12{float:left}.col-md-1{width:8.33333%}.col-md-2{width:16.66667%}.col-md-3{width:25%}.col-md-4{width:33.33333%}.col-md-5,#metadata dl dt{width:41.66667%}.col-md-6{width:50%}.col-md-7,#metadata dl dd{width:58.33333%}.col-md-8{width:66.66667%}.col-md-9{width:75%}.col-md-10{width:83.33333%}.col-md-11{width:91.66667%}.col-md-12{width:100%}.col-md-pull-0{right:auto}.col-md-pull-1{right:8.33333%}.col-md-pull-2{right:16.66667%}.col-md-pull-3{right:25%}.col-md-pull-4{right:33.33333%}.col-md-pull-5{right:41.66667%}.col-md-pull-6{right:50%}.col-md-pull-7{right:58.33333%}.col-md-pull-8{right:66.66667%}.col-md-pull-9{right:75%}.col-md-pull-10{right:83.33333%}.col-md-pull-11{right:91.66667%}.col-md-pull-12{right:100%}.col-md-push-0{left:auto}.col-md-push-1{left:8.33333%}.col-md-push-2{left:16.66667%}.col-md-push-3{left:25%}.col-md-push-4{left:33.33333%}.col-md-push-5{left:41.66667%}.col-md-push-6{left:50%}.col-md-push-7{left:58.33333%}.col-md-push-8{left:66.66667%}.col-md-push-9{left:75%}.col-md-push-10{left:83.33333%}.col-md-push-11{left:91.66667%}.col-md-push-12{left:100%}.col-md-offset-0{margin-left:0%}.col-md-offset-1{margin-left:8.33333%}.col-md-offset-2{margin-left:16.66667%}.col-md-offset-3{margin-left:25%}.col-md-offset-4{margin-left:33.33333%}.col-md-offset-5{margin-left:41.66667%}.col-md-offset-6{margin-left:50%}.col-md-offset-7{margin-left:58.33333%}.col-md-offset-8{margin-left:66.66667%}.col-md-offset-9{margin-left:75%}.col-md-offset-10{margin-left:83.33333%}.col-md-offset-11{margin-left:91.66667%}.col-md-offset-12{margin-left:100%}}@media (min-width: 1200px){.col-lg-1,.col-lg-2,.col-lg-3,.col-lg-4,.col-lg-5,.col-lg-6,.col-lg-7,.col-lg-8,.col-lg-9,.col-lg-10,.col-lg-11,.col-lg-12{float:left}.col-lg-1{width:8.33333%}.col-lg-2{width:16.66667%}.col-lg-3{width:25%}.col-lg-4{width:33.33333%}.col-lg-5{width:41.66667%}.col-lg-6{width:50%}.col-lg-7{width:58.33333%}.col-lg-8{width:66.66667%}.col-lg-9{width:75%}.col-lg-10{width:83.33333%}.col-lg-11{width:91.66667%}.col-lg-12{width:100%}.col-lg-pull-0{right:auto}.col-lg-pull-1{right:8.33333%}.col-lg-pull-2{right:16.66667%}.col-lg-pull-3{right:25%}.col-lg-pull-4{right:33.33333%}.col-lg-pull-5{right:41.66667%}.col-lg-pull-6{right:50%}.col-lg-pull-7{right:58.33333%}.col-lg-pull-8{right:66.66667%}.col-lg-pull-9{right:75%}.col-lg-pull-10{right:83.33333%}.col-lg-pull-11{right:91.66667%}.col-lg-pull-12{right:100%}.col-lg-push-0{left:auto}.col-lg-push-1{left:8.33333%}.col-lg-push-2{left:16.66667%}.col-lg-push-3{left:25%}.col-lg-push-4{left:33.33333%}.col-lg-push-5{left:41.66667%}.col-lg-push-6{left:50%}.col-lg-push-7{left:58.33333%}.col-lg-push-8{left:66.66667%}.col-lg-push-9{left:75%}.col-lg-push-10{left:83.33333%}.col-lg-push-11{left:91.66667%}.col-lg-push-12{left:100%}.col-lg-offset-0{margin-left:0%}.col-lg-offset-1{margin-left:8.33333%}.col-lg-offset-2{margin-left:16.66667%}.col-lg-offset-3{margin-left:25%}.col-lg-offset-4{margin-left:33.33333%}.col-lg-offset-5{margin-left:41.66667%}.col-lg-offset-6{margin-left:50%}.col-lg-offset-7{margin-left:58.33333%}.col-lg-offset-8{margin-left:66.66667%}.col-lg-offset-9{margin-left:75%}.col-lg-offset-10{margin-left:83.33333%}.col-lg-offset-11{margin-left:91.66667%}.col-lg-offset-12{margin-left:100%}}table{background-color:transparent}caption{padding-top:8px;padding-bottom:8px;color:#777;text-align:left}th{text-align:left}.table,#tax table{width:100%;max-width:100%;margin-bottom:20px}.table&gt;thead&gt;tr&gt;th,#tax table&gt;thead&gt;tr&gt;th,.table&gt;thead&gt;tr&gt;td,#tax table&gt;thead&gt;tr&gt;td,.table&gt;tbody&gt;tr&gt;th,#tax table&gt;tbody&gt;tr&gt;th,.table&gt;tbody&gt;tr&gt;td,#tax table&gt;tbody&gt;tr&gt;td,.table&gt;tfoot&gt;tr&gt;th,#tax table&gt;tfoot&gt;tr&gt;th,.table&gt;tfoot&gt;tr&gt;td,#tax table&gt;tfoot&gt;tr&gt;td{padding:8px;line-height:1.42857;vertical-align:top;border-top:1px solid #ddd}.table&gt;thead&gt;tr&gt;th,#tax table&gt;thead&gt;tr&gt;th{vertical-align:bottom;border-bottom:2px solid #ddd}.table&gt;caption+thead&gt;tr:first-child&gt;th,#tax table&gt;caption+thead&gt;tr:first-child&gt;th,.table&gt;caption+thead&gt;tr:first-child&gt;td,#tax table&gt;caption+thead&gt;tr:first-child&gt;td,.table&gt;colgroup+thead&gt;tr:first-child&gt;th,#tax table&gt;colgroup+thead&gt;tr:first-child&gt;th,.table&gt;colgroup+thead&gt;tr:first-child&gt;td,#tax table&gt;colgroup+thead&gt;tr:first-child&gt;td,.table&gt;thead:first-child&gt;tr:first-child&gt;th,#tax table&gt;thead:first-child&gt;tr:first-child&gt;th,.table&gt;thead:first-child&gt;tr:first-child&gt;td,#tax table&gt;thead:first-child&gt;tr:first-child&gt;td{border-top:0}.table&gt;tbody+tbody,#tax table&gt;tbody+tbody{border-top:2px solid #ddd}.table .table,#tax table .table,.table #tax table,#tax .table table,#tax table table{background-color:#eee}.table-condensed&gt;thead&gt;tr&gt;th,#tax table&gt;thead&gt;tr&gt;th,.table-condensed&gt;thead&gt;tr&gt;td,#tax table&gt;thead&gt;tr&gt;td,.table-condensed&gt;tbody&gt;tr&gt;th,#tax table&gt;tbody&gt;tr&gt;th,.table-condensed&gt;tbody&gt;tr&gt;td,#tax table&gt;tbody&gt;tr&gt;td,.table-condensed&gt;tfoot&gt;tr&gt;th,#tax table&gt;tfoot&gt;tr&gt;th,.table-condensed&gt;tfoot&gt;tr&gt;td,#tax table&gt;tfoot&gt;tr&gt;td{padding:5px}.table-bordered{border:1px solid #ddd}.table-bordered&gt;thead&gt;tr&gt;th,.table-bordered&gt;thead&gt;tr&gt;td,.table-bordered&gt;tbody&gt;tr&gt;th,.table-bordered&gt;tbody&gt;tr&gt;td,.table-bordered&gt;tfoot&gt;tr&gt;th,.table-bordered&gt;tfoot&gt;tr&gt;td{border:1px solid #ddd}.table-bordered&gt;thead&gt;tr&gt;th,.table-bordered&gt;thead&gt;tr&gt;td{border-bottom-width:2px}.table-striped&gt;tbody&gt;tr:nth-of-type(odd),#tax table&gt;tbody&gt;tr:nth-of-type(odd){background-color:#f9f9f9}.table-hover&gt;tbody&gt;tr:hover{background-color:#f5f5f5}table col[class*="col-"]{position:static;float:none;display:table-column}table td[class*="col-"],table th[class*="col-"]{position:static;float:none;display:table-cell}.table&gt;thead&gt;tr&gt;td.active,#tax table&gt;thead&gt;tr&gt;td.active,.table&gt;thead&gt;tr&gt;th.active,#tax table&gt;thead&gt;tr&gt;th.active,.table&gt;thead&gt;tr.active&gt;td,#tax table&gt;thead&gt;tr.active&gt;td,.table&gt;thead&gt;tr.active&gt;th,#tax table&gt;thead&gt;tr.active&gt;th,.table&gt;tbody&gt;tr&gt;td.active,#tax table&gt;tbody&gt;tr&gt;td.active,.table&gt;tbody&gt;tr&gt;th.active,#tax table&gt;tbody&gt;tr&gt;th.active,.table&gt;tbody&gt;tr.active&gt;td,#tax table&gt;tbody&gt;tr.active&gt;td,.table&gt;tbody&gt;tr.active&gt;th,#tax table&gt;tbody&gt;tr.active&gt;th,.table&gt;tfoot&gt;tr&gt;td.active,#tax table&gt;tfoot&gt;tr&gt;td.active,.table&gt;tfoot&gt;tr&gt;th.active,#tax table&gt;tfoot&gt;tr&gt;th.active,.table&gt;tfoot&gt;tr.active&gt;td,#tax table&gt;tfoot&gt;tr.active&gt;td,.table&gt;tfoot&gt;tr.active&gt;th,#tax table&gt;tfoot&gt;tr.active&gt;th{background-color:#f5f5f5}.table-hover&gt;tbody&gt;tr&gt;td.active:hover,.table-hover&gt;tbody&gt;tr&gt;th.active:hover,.table-hover&gt;tbody&gt;tr.active:hover&gt;td,.table-hover&gt;tbody&gt;tr:hover&gt;.active,.table-hover&gt;tbody&gt;tr.active:hover&gt;th{background-color:#e8e8e8}.table&gt;thead&gt;tr&gt;td.success,#tax table&gt;thead&gt;tr&gt;td.success,.table&gt;thead&gt;tr&gt;th.success,#tax table&gt;thead&gt;tr&gt;th.success,.table&gt;thead&gt;tr.success&gt;td,#tax table&gt;thead&gt;tr.success&gt;td,.table&gt;thead&gt;tr.success&gt;th,#tax table&gt;thead&gt;tr.success&gt;th,.table&gt;tbody&gt;tr&gt;td.success,#tax table&gt;tbody&gt;tr&gt;td.success,.table&gt;tbody&gt;tr&gt;th.success,#tax table&gt;tbody&gt;tr&gt;th.success,.table&gt;tbody&gt;tr.success&gt;td,#tax table&gt;tbody&gt;tr.success&gt;td,.table&gt;tbody&gt;tr.success&gt;th,#tax table&gt;tbody&gt;tr.success&gt;th,.table&gt;tfoot&gt;tr&gt;td.success,#tax table&gt;tfoot&gt;tr&gt;td.success,.table&gt;tfoot&gt;tr&gt;th.success,#tax table&gt;tfoot&gt;tr&gt;th.success,.table&gt;tfoot&gt;tr.success&gt;td,#tax table&gt;tfoot&gt;tr.success&gt;td,.table&gt;tfoot&gt;tr.success&gt;th,#tax table&gt;tfoot&gt;tr.success&gt;th{background-color:#dff0d8}.table-hover&gt;tbody&gt;tr&gt;td.success:hover,.table-hover&gt;tbody&gt;tr&gt;th.success:hover,.table-hover&gt;tbody&gt;tr.success:hover&gt;td,.table-hover&gt;tbody&gt;tr:hover&gt;.success,.table-hover&gt;tbody&gt;tr.success:hover&gt;th{background-color:#d0e9c6}.table&gt;thead&gt;tr&gt;td.info,#tax table&gt;thead&gt;tr&gt;td.info,.table&gt;thead&gt;tr&gt;th.info,#tax table&gt;thead&gt;tr&gt;th.info,.table&gt;thead&gt;tr.info&gt;td,#tax table&gt;thead&gt;tr.info&gt;td,.table&gt;thead&gt;tr.info&gt;th,#tax table&gt;thead&gt;tr.info&gt;th,.table&gt;tbody&gt;tr&gt;td.info,#tax table&gt;tbody&gt;tr&gt;td.info,.table&gt;tbody&gt;tr&gt;th.info,#tax table&gt;tbody&gt;tr&gt;th.info,.table&gt;tbody&gt;tr.info&gt;td,#tax table&gt;tbody&gt;tr.info&gt;td,.table&gt;tbody&gt;tr.info&gt;th,#tax table&gt;tbody&gt;tr.info&gt;th,.table&gt;tfoot&gt;tr&gt;td.info,#tax table&gt;tfoot&gt;tr&gt;td.info,.table&gt;tfoot&gt;tr&gt;th.info,#tax table&gt;tfoot&gt;tr&gt;th.info,.table&gt;tfoot&gt;tr.info&gt;td,#tax table&gt;tfoot&gt;tr.info&gt;td,.table&gt;tfoot&gt;tr.info&gt;th,#tax table&gt;tfoot&gt;tr.info&gt;th{background-color:#d9edf7}.table-hover&gt;tbody&gt;tr&gt;td.info:hover,.table-hover&gt;tbody&gt;tr&gt;th.info:hover,.table-hover&gt;tbody&gt;tr.info:hover&gt;td,.table-hover&gt;tbody&gt;tr:hover&gt;.info,.table-hover&gt;tbody&gt;tr.info:hover&gt;th{background-color:#c4e3f3}.table&gt;thead&gt;tr&gt;td.warning,#tax table&gt;thead&gt;tr&gt;td.warning,.table&gt;thead&gt;tr&gt;th.warning,#tax table&gt;thead&gt;tr&gt;th.warning,.table&gt;thead&gt;tr.warning&gt;td,#tax table&gt;thead&gt;tr.warning&gt;td,.table&gt;thead&gt;tr.warning&gt;th,#tax table&gt;thead&gt;tr.warning&gt;th,.table&gt;tbody&gt;tr&gt;td.warning,#tax table&gt;tbody&gt;tr&gt;td.warning,.table&gt;tbody&gt;tr&gt;th.warning,#tax table&gt;tbody&gt;tr&gt;th.warning,.table&gt;tbody&gt;tr.warning&gt;td,#tax table&gt;tbody&gt;tr.warning&gt;td,.table&gt;tbody&gt;tr.warning&gt;th,#tax table&gt;tbody&gt;tr.warning&gt;th,.table&gt;tfoot&gt;tr&gt;td.warning,#tax table&gt;tfoot&gt;tr&gt;td.warning,.table&gt;tfoot&gt;tr&gt;th.warning,#tax table&gt;tfoot&gt;tr&gt;th.warning,.table&gt;tfoot&gt;tr.warning&gt;td,#tax table&gt;tfoot&gt;tr.warning&gt;td,.table&gt;tfoot&gt;tr.warning&gt;th,#tax table&gt;tfoot&gt;tr.warning&gt;th{background-color:#fcf8e3}.table-hover&gt;tbody&gt;tr&gt;td.warning:hover,.table-hover&gt;tbody&gt;tr&gt;th.warning:hover,.table-hover&gt;tbody&gt;tr.warning:hover&gt;td,.table-hover&gt;tbody&gt;tr:hover&gt;.warning,.table-hover&gt;tbody&gt;tr.warning:hover&gt;th{background-color:#faf2cc}.table&gt;thead&gt;tr&gt;td.danger,#tax table&gt;thead&gt;tr&gt;td.danger,.table&gt;thead&gt;tr&gt;th.danger,#tax table&gt;thead&gt;tr&gt;th.danger,.table&gt;thead&gt;tr.danger&gt;td,#tax table&gt;thead&gt;tr.danger&gt;td,.table&gt;thead&gt;tr.danger&gt;th,#tax table&gt;thead&gt;tr.danger&gt;th,.table&gt;tbody&gt;tr&gt;td.danger,#tax table&gt;tbody&gt;tr&gt;td.danger,.table&gt;tbody&gt;tr&gt;th.danger,#tax table&gt;tbody&gt;tr&gt;th.danger,.table&gt;tbody&gt;tr.danger&gt;td,#tax table&gt;tbody&gt;tr.danger&gt;td,.table&gt;tbody&gt;tr.danger&gt;th,#tax table&gt;tbody&gt;tr.danger&gt;th,.table&gt;tfoot&gt;tr&gt;td.danger,#tax table&gt;tfoot&gt;tr&gt;td.danger,.table&gt;tfoot&gt;tr&gt;th.danger,#tax table&gt;tfoot&gt;tr&gt;th.danger,.table&gt;tfoot&gt;tr.danger&gt;td,#tax table&gt;tfoot&gt;tr.danger&gt;td,.table&gt;tfoot&gt;tr.danger&gt;th,#tax table&gt;tfoot&gt;tr.danger&gt;th{background-color:#f2dede}.table-hover&gt;tbody&gt;tr&gt;td.danger:hover,.table-hover&gt;tbody&gt;tr&gt;th.danger:hover,.table-hover&gt;tbody&gt;tr.danger:hover&gt;td,.table-hover&gt;tbody&gt;tr:hover&gt;.danger,.table-hover&gt;tbody&gt;tr.danger:hover&gt;th{background-color:#ebcccc}.table-responsive{overflow-x:auto;min-height:0.01%}@media screen and (max-width: 534px){.table-responsive{width:100%;margin-bottom:15px;overflow-y:hidden;-ms-overflow-style:-ms-autohiding-scrollbar;border:1px solid #ddd}.table-responsive&gt;.table,#tax .table-responsive&gt;table{margin-bottom:0}.table-responsive&gt;.table&gt;thead&gt;tr&gt;th,#tax .table-responsive&gt;table&gt;thead&gt;tr&gt;th,.table-responsive&gt;.table&gt;thead&gt;tr&gt;td,#tax .table-responsive&gt;table&gt;thead&gt;tr&gt;td,.table-responsive&gt;.table&gt;tbody&gt;tr&gt;th,#tax .table-responsive&gt;table&gt;tbody&gt;tr&gt;th,.table-responsive&gt;.table&gt;tbody&gt;tr&gt;td,#tax .table-responsive&gt;table&gt;tbody&gt;tr&gt;td,.table-responsive&gt;.table&gt;tfoot&gt;tr&gt;th,#tax .table-responsive&gt;table&gt;tfoot&gt;tr&gt;th,.table-responsive&gt;.table&gt;tfoot&gt;tr&gt;td,#tax .table-responsive&gt;table&gt;tfoot&gt;tr&gt;td{white-space:nowrap}.table-responsive&gt;.table-bordered{border:0}.table-responsive&gt;.table-bordered&gt;thead&gt;tr&gt;th:first-child,.table-responsive&gt;.table-bordered&gt;thead&gt;tr&gt;td:first-child,.table-responsive&gt;.table-bordered&gt;tbody&gt;tr&gt;th:first-child,.table-responsive&gt;.table-bordered&gt;tbody&gt;tr&gt;td:first-child,.table-responsive&gt;.table-bordered&gt;tfoot&gt;tr&gt;th:first-child,.table-responsive&gt;.table-bordered&gt;tfoot&gt;tr&gt;td:first-child{border-left:0}.table-responsive&gt;.table-bordered&gt;thead&gt;tr&gt;th:last-child,.table-responsive&gt;.table-bordered&gt;thead&gt;tr&gt;td:last-child,.table-responsive&gt;.table-bordered&gt;tbody&gt;tr&gt;th:last-child,.table-responsive&gt;.table-bordered&gt;tbody&gt;tr&gt;td:last-child,.table-responsive&gt;.table-bordered&gt;tfoot&gt;tr&gt;th:last-child,.table-responsive&gt;.table-bordered&gt;tfoot&gt;tr&gt;td:last-child{border-right:0}.table-responsive&gt;.table-bordered&gt;tbody&gt;tr:last-child&gt;th,.table-responsive&gt;.table-bordered&gt;tbody&gt;tr:last-child&gt;td,.table-responsive&gt;.table-bordered&gt;tfoot&gt;tr:last-child&gt;th,.table-responsive&gt;.table-bordered&gt;tfoot&gt;tr:last-child&gt;td{border-bottom:0}}.label{display:inline;padding:.2em .6em .3em;font-size:75%;font-weight:bold;line-height:1;color:#fff;text-align:center;white-space:nowrap;vertical-align:baseline;border-radius:.25em}.label:empty{display:none}.btn .label{position:relative;top:-1px}a.label:hover,a.label:focus{color:#fff;text-decoration:none;cursor:pointer}.label-default{background-color:#777}.label-default[href]:hover,.label-default[href]:focus{background-color:#5e5e5e}.label-primary{background-color:#337ab7}.label-primary[href]:hover,.label-primary[href]:focus{background-color:#286090}.label-success{background-color:#5cb85c}.label-success[href]:hover,.label-success[href]:focus{background-color:#449d44}.label-info{background-color:#5bc0de}.label-info[href]:hover,.label-info[href]:focus{background-color:#31b0d5}.label-warning{background-color:#f0ad4e}.label-warning[href]:hover,.label-warning[href]:focus{background-color:#ec971f}.label-danger{background-color:#d9534f}.label-danger[href]:hover,.label-danger[href]:focus{background-color:#c9302c}.badge{display:inline-block;min-width:10px;padding:3px 7px;font-size:12px;font-weight:bold;color:#fff;line-height:1;vertical-align:middle;white-space:nowrap;text-align:center;background-color:#777;border-radius:10px}.badge:empty{display:none}.btn .badge{position:relative;top:-1px}.btn-xs .badge,.btn-group-xs&gt;.btn .badge{top:0;padding:1px 5px}.list-group-item.active&gt;.badge,.nav-pills&gt;.active&gt;a&gt;.badge{color:#337ab7;background-color:#fff}.list-group-item&gt;.badge{float:right}.list-group-item&gt;.badge+.badge{margin-right:5px}.nav-pills&gt;li&gt;a&gt;.badge{margin-left:3px}a.badge:hover,a.badge:focus{color:#fff;text-decoration:none;cursor:pointer}.clearfix:before,.clearfix:after{content:" ";display:table}.clearfix:after{clear:both}.center-block{display:block;margin-left:auto;margin-right:auto}.pull-right{float:right !important}.pull-left{float:left !important}.hide{display:none !important}.show{display:block !important}.invisible{visibility:hidden}.text-hide{font:0/0 a;color:transparent;text-shadow:none;background-color:transparent;border:0}.hidden{display:none !important}.affix{position:fixed}@-ms-viewport{width:device-width}.visible-xs{display:none !important}.visible-sm{display:none !important}.visible-md{display:none !important}.visible-lg{display:none !important}.visible-xs-block,.visible-xs-inline,.visible-xs-inline-block,.visible-sm-block,.visible-sm-inline,.visible-sm-inline-block,.visible-md-block,.visible-md-inline,.visible-md-inline-block,.visible-lg-block,.visible-lg-inline,.visible-lg-inline-block{display:none !important}@media (max-width: 534px){.visible-xs{display:block !important}table.visible-xs{display:table !important}tr.visible-xs{display:table-row !important}th.visible-xs,td.visible-xs{display:table-cell !important}}@media (max-width: 534px){.visible-xs-block{display:block !important}}@media (max-width: 534px){.visible-xs-inline{display:inline !important}}@media (max-width: 534px){.visible-xs-inline-block{display:inline-block !important}}@media (min-width: 535px) and (max-width: 991px){.visible-sm{display:block !important}table.visible-sm{display:table !important}tr.visible-sm{display:table-row !important}th.visible-sm,td.visible-sm{display:table-cell !important}}@media (min-width: 535px) and (max-width: 991px){.visible-sm-block{display:block !important}}@media (min-width: 535px) and (max-width: 991px){.visible-sm-inline{display:inline !important}}@media (min-width: 535px) and (max-width: 991px){.visible-sm-inline-block{display:inline-block !important}}@media (min-width: 992px) and (max-width: 1199px){.visible-md{display:block !important}table.visible-md{display:table !important}tr.visible-md{display:table-row !important}th.visible-md,td.visible-md{display:table-cell !important}}@media (min-width: 992px) and (max-width: 1199px){.visible-md-block{display:block !important}}@media (min-width: 992px) and (max-width: 1199px){.visible-md-inline{display:inline !important}}@media (min-width: 992px) and (max-width: 1199px){.visible-md-inline-block{display:inline-block !important}}@media (min-width: 1200px){.visible-lg{display:block !important}table.visible-lg{display:table !important}tr.visible-lg{display:table-row !important}th.visible-lg,td.visible-lg{display:table-cell !important}}@media (min-width: 1200px){.visible-lg-block{display:block !important}}@media (min-width: 1200px){.visible-lg-inline{display:inline !important}}@media (min-width: 1200px){.visible-lg-inline-block{display:inline-block !important}}@media (max-width: 534px){.hidden-xs{display:none !important}}@media (min-width: 535px) and (max-width: 991px){.hidden-sm{display:none !important}}@media (min-width: 992px) and (max-width: 1199px){.hidden-md{display:none !important}}@media (min-width: 1200px){.hidden-lg{display:none !important}}.visible-print{display:none !important}@media print{.visible-print{display:block !important}table.visible-print{display:table !important}tr.visible-print{display:table-row !important}th.visible-print,td.visible-print{display:table-cell !important}}.visible-print-block{display:none !important}@media print{.visible-print-block{display:block !important}}.visible-print-inline{display:none !important}@media print{.visible-print-inline{display:inline !important}}.visible-print-inline-block{display:none !important}@media print{.visible-print-inline-block{display:inline-block !important}}@media print{.hidden-print{display:none !important}}#document{background-color:#fff;border-left:1px solid #ccc;border-right:1px solid #ccc}@media print{#document{width:100%;padding:0;border:0}}hr{display:none;margin:5pt 0}div.row,div.line{clear:both;page-break-inside:avoid}div.line{margin-bottom:5pt}dt{padding-top:2pt;clear:both;margin-bottom:3pt}dd{margin-bottom:3pt}div.linesupport{background-color:#eee !important;padding:4pt 5pt 2pt;margin:2pt 0}@media print{div.linesupport{background-color:#eee !important;-webkit-print-color-adjust:exact}}div.linetotal{border-bottom:1px solid #999;border-top:1px solid #f0f0f0;padding:4pt 0 2pt;margin:5pt 0 10pt}div.total{border-bottom:2px solid #999;font-weight:bold;padding:4pt 0 2pt;margin:-5pt 0 15pt}p.note{font-style:italic}#footer{margin:20pt 0 5pt}@media print{#footer{display:none}}@media print{a[href]:after{content:"" !important}}h3{border-bottom:1px solid #ccc}#logo{max-height:60pt;max-width:250pt;float:right;margin-bottom:10pt;margin-top:10pt}@media (max-width: 535px){#logo{max-height:35pt;max-width:125pt;margin-right:5pt;margin-bottom:10pt}}#attachments ul{padding-left:0}#attachments ul li{list-style-type:none;margin-bottom:5pt}#totals dl dd{margin-bottom:10pt}.line .details dl,.line .info dl{margin-bottom:10pt}span.mtr{color:#c9302c}</style>
			</xsl:when>
			<xsl:otherwise>
				<link rel="stylesheet" href="{$stylesheet_url}"/>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>
	<xsl:template name="doc-footer">
		<div id="footer">
			<div>Document: <xsl:value-of select="namespace-uri()"/>::<xsl:value-of select="local-name()"/>
			</div>
			<div>Customization: <xsl:value-of select="cbc:CustomizationID"/>
			</div>
			<div>Profile: <xsl:value-of select="cbc:ProfileID"/>
			</div>
		</div>
	</xsl:template>
	<xsl:template match="cn:CreditNote[starts-with(normalize-space(cbc:CustomizationID/text()), 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0')]" mode="document" priority="1000">
		<html lang="{$language}">
			<head>
				<xsl:call-template name="doc-head"/>
				<title>
					<xsl:value-of select="u:label('document', local-name())"/>
				</title>
			</head>
			<body>
				<div id="document">
					<h1>
						<xsl:copy-of select="u:label('document', local-name())"/>
						<br/>
						<small>
							<xsl:value-of select="u:codelist('uncl1001-cn', cbc:CreditNoteTypeCode/text())"/>
						</small>
					</h1>
					<div class="row">
						<div id="parties" class="col-sm-6 col-md-7">
							<h3>
								<xsl:copy-of select="u:label('party', 'Supplier')"/>
							</h3>
							<xsl:apply-templates select="cac:AccountingSupplierParty/cac:Party" mode="party-with-contact"/>
							<h3>
								<xsl:copy-of select="u:label('party', 'Customer')"/>
							</h3>
							<xsl:apply-templates select="cac:AccountingCustomerParty/cac:Party" mode="party-with-contact"/>
						</div>
						<div id="metadata" class="col-sm-6 col-md-5">
							<xsl:call-template name="metadata"/>
							<xsl:apply-templates select="cbc:Note" mode="common"/>
						</div>
					</div>
					<div class="row">
						<div id="totals" class="col-sm-4">
							<xsl:apply-templates select="cac:LegalMonetaryTotal" mode="total"/>
						</div>
						<div id="delivery" class="col-sm-4">
							<xsl:call-template name="delivery-block"/>
						</div>
						<div id="attachments" class="col-sm-4">
							<xsl:call-template name="attachments-block"/>
						</div>
					</div>
					<div class="row">
						<div id="tax" class="col-sm-6">
							<h3>
								<xsl:copy-of select="u:label('tax', 'Tax')"/>
							</h3>
							<xsl:apply-templates select="cac:TaxTotal[cac:TaxSubtotal]" mode="tax"/>
							<xsl:apply-templates select="cac:TaxRepresentativeParty" mode="party"/>
						</div>
						<div id="payment" class="col-sm-6">
							<h3>
								<xsl:copy-of select="u:label('payment', 'Payment')"/>
							</h3>
							<xsl:apply-templates select="cac:PaymentMeans" mode="payment"/>
							<xsl:apply-templates select="cac:PayeeParty" mode="party"/>
							<xsl:apply-templates select="cac:PaymentTerms" mode="payment"/>
						</div>
					</div>
					<hr/>
					<div id="details">
						<h3>Details</h3>
						<xsl:apply-templates select="cac:AllowanceCharge[cbc:ChargeIndicator='true']" mode="line"/>
						<xsl:apply-templates select="cac:LegalMonetaryTotal/cbc:ChargeTotalAmount" mode="line"/>
						<xsl:apply-templates select="cac:AllowanceCharge[cbc:ChargeIndicator='false']" mode="line"/>
						<xsl:apply-templates select="cac:LegalMonetaryTotal/cbc:AllowanceTotalAmount" mode="line"/>
						<xsl:apply-templates select="cac:CreditNoteLine" mode="line"/>
						<xsl:apply-templates select="cac:LegalMonetaryTotal/cbc:LineExtensionAmount" mode="line"/>
					</div>
					<xsl:call-template name="doc-footer"/>
				</div>
			</body>
		</html>
	</xsl:template>
	<xsl:template match="in:Invoice[starts-with(normalize-space(cbc:CustomizationID/text()), 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0')]" mode="document" priority="1000">
		<html lang="{$language}">
			<head>
				<xsl:call-template name="doc-head"/>
				<title>
					<xsl:value-of select="u:label('document', local-name())"/>
				</title>
			</head>
			<body>
				<div id="document">
					<h1>
						<xsl:copy-of select="u:label('document', local-name())"/>
						<br/>
						<small>
							<xsl:value-of select="u:codelist('uncl1001invoice', cbc:InvoiceTypeCode/text())"/>
						</small>
					</h1>
					<div class="row">
						<div id="parties" class="col-sm-6 col-md-7">
							<h3>
								<xsl:copy-of select="u:label('party', 'Supplier')"/>
							</h3>
							<xsl:apply-templates select="cac:AccountingSupplierParty/cac:Party" mode="party-with-contact"/>
							<h3>
								<xsl:copy-of select="u:label('party', 'Customer')"/>
							</h3>
							<xsl:apply-templates select="cac:AccountingCustomerParty/cac:Party" mode="party-with-contact"/>
						</div>
						<div id="metadata" class="col-sm-6 col-md-5">
							<xsl:call-template name="metadata"/>
							<xsl:apply-templates select="cbc:Note" mode="common"/>
						</div>
					</div>
					<div class="row">
						<div id="totals" class="col-sm-4">
							<xsl:apply-templates select="cac:LegalMonetaryTotal" mode="total"/>
						</div>
						<div id="delivery" class="col-sm-4">
							<xsl:call-template name="delivery-block"/>
						</div>
						<div id="attachments" class="col-sm-4">
							<xsl:call-template name="attachments-block"/>
						</div>
					</div>
					<div class="row">
						<div id="tax" class="col-sm-6">
							<h3>
								<xsl:copy-of select="u:label('tax', 'Tax')"/>
							</h3>
							<xsl:apply-templates select="cac:TaxTotal[cac:TaxSubtotal]" mode="tax"/>
							<xsl:apply-templates select="cac:TaxRepresentativeParty" mode="party"/>
						</div>
						<div id="payment" class="col-sm-6">
							<h3>
								<xsl:copy-of select="u:label('payment', 'Payment')"/>
							</h3>
							<xsl:apply-templates select="cac:PaymentMeans" mode="payment"/>
							<xsl:apply-templates select="cac:PayeeParty" mode="party"/>
							<xsl:apply-templates select="cac:PaymentTerms" mode="payment"/>
						</div>
					</div>
					<hr/>
					<div id="details">
						<h3>Details</h3>
						<xsl:apply-templates select="cac:AllowanceCharge[cbc:ChargeIndicator='true']" mode="line"/>
						<xsl:apply-templates select="cac:LegalMonetaryTotal/cbc:ChargeTotalAmount" mode="line"/>
						<xsl:apply-templates select="cac:AllowanceCharge[cbc:ChargeIndicator='false']" mode="line"/>
						<xsl:apply-templates select="cac:LegalMonetaryTotal/cbc:AllowanceTotalAmount" mode="line"/>
						<xsl:apply-templates select="cac:InvoiceLine" mode="line"/>
						<xsl:apply-templates select="cac:LegalMonetaryTotal/cbc:LineExtensionAmount" mode="line"/>
					</div>
					<xsl:call-template name="doc-footer"/>
				</div>
			</body>
		</html>
	</xsl:template>
	<xsl:function name="u:codelist">
		<xsl:param name="codelist"/>
		<xsl:param name="code"/>
		<xsl:choose>
			<xsl:when test="$codelists/cl[@id=$codelist]/c[@id=$code]/t[@id=$language]">
				<xsl:value-of select="$codelists/cl[@id=$codelist]/c[@id=$code]/t[@id=$language]/text()"/>
			</xsl:when>
			<xsl:otherwise>
				<span class="mtr">[code:<xsl:value-of select="$codelist"/>:<xsl:value-of select="$code"/>]</span>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:function>
	<xsl:variable name="codelists">
		<cl id="iso3166">
			<c id="AD">
				<t id="en">Andorra</t>
				<t id="no">Andorra</t>
			</c>
			<c id="AE">
				<t id="en">United Arab Emirates</t>
				<t id="no">De forente arabiske emirater</t>
			</c>
			<c id="AF">
				<t id="en">Afghanistan</t>
				<t id="no">Afghanistan</t>
			</c>
			<c id="AG">
				<t id="en">Antigua and Barbuda</t>
				<t id="no">Antigua og Barbuda</t>
			</c>
			<c id="AI">
				<t id="en">Anguilla</t>
				<t id="no">Anguilla</t>
			</c>
			<c id="AL">
				<t id="en">Albania</t>
				<t id="no">Albania</t>
			</c>
			<c id="AM">
				<t id="en">Armenia</t>
				<t id="no">Armenia</t>
			</c>
			<c id="AO">
				<t id="en">Angola</t>
				<t id="no">Angola</t>
			</c>
			<c id="AQ">
				<t id="en">Antarctica</t>
				<t id="no">Antarktis</t>
			</c>
			<c id="AR">
				<t id="en">Argentina</t>
				<t id="no">Argentina</t>
			</c>
			<c id="AS">
				<t id="en">American Samoa</t>
				<t id="no">Amerikansk Samoa</t>
			</c>
			<c id="AT">
				<t id="en">Austria</t>
				<t id="no">Østerrike</t>
			</c>
			<c id="AU">
				<t id="en">Australia</t>
				<t id="no">Australia</t>
			</c>
			<c id="AW">
				<t id="en">Aruba</t>
				<t id="no">Aruba</t>
			</c>
			<c id="AX">
				<t id="en">Åland Islands</t>
				<t id="no">Åland</t>
			</c>
			<c id="AZ">
				<t id="en">Azerbaijan</t>
				<t id="no">Aserbajdsjan</t>
			</c>
			<c id="BA">
				<t id="en">Bosnia and Herzegovina</t>
				<t id="no">Bosnia-Hercegovina</t>
			</c>
			<c id="BB">
				<t id="en">Barbados</t>
				<t id="no">Barbados</t>
			</c>
			<c id="BD">
				<t id="en">Bangladesh</t>
				<t id="no">Bangladesh</t>
			</c>
			<c id="BE">
				<t id="en">Belgium</t>
				<t id="no">Belgia</t>
			</c>
			<c id="BF">
				<t id="en">Burkina Faso</t>
				<t id="no">Burkina Faso</t>
			</c>
			<c id="BG">
				<t id="en">Bulgaria</t>
				<t id="no">Bulgaria</t>
			</c>
			<c id="BH">
				<t id="en">Bahrain</t>
				<t id="no">Bahrain</t>
			</c>
			<c id="BI">
				<t id="en">Burundi</t>
				<t id="no">Burundi</t>
			</c>
			<c id="BJ">
				<t id="en">Benin</t>
				<t id="no">Benin</t>
			</c>
			<c id="BL">
				<t id="en">Saint Barthélemy</t>
				<t id="no">Saint-Barthélemy</t>
			</c>
			<c id="BM">
				<t id="en">Bermuda</t>
				<t id="no">Bermuda</t>
			</c>
			<c id="BN">
				<t id="en">Brunei Darussalam</t>
				<t id="no">Brunei</t>
			</c>
			<c id="BO">
				<t id="en">Bolivia, Plurinational State of</t>
				<t id="no">Bolivia</t>
			</c>
			<c id="BQ">
				<t id="en">Bonaire, Sint Eustatius and Saba</t>
				<t id="no">Bonaire, Sint Eustatius og Saba</t>
			</c>
			<c id="BR">
				<t id="en">Brazil</t>
				<t id="no">Brasil</t>
			</c>
			<c id="BS">
				<t id="en">Bahamas</t>
				<t id="no">Bahamas</t>
			</c>
			<c id="BT">
				<t id="en">Bhutan</t>
				<t id="no">Bhutan</t>
			</c>
			<c id="BV">
				<t id="en">Bouvet Island</t>
				<t id="no">Bouvetøya</t>
			</c>
			<c id="BW">
				<t id="en">Botswana</t>
				<t id="no">Botswana</t>
			</c>
			<c id="BY">
				<t id="en">Belarus</t>
				<t id="no">Hviterussland</t>
			</c>
			<c id="BZ">
				<t id="en">Belize</t>
				<t id="no">Belize</t>
			</c>
			<c id="CA">
				<t id="en">Canada</t>
				<t id="no">Canada</t>
			</c>
			<c id="CC">
				<t id="en">Cocos (Keeling) Islands</t>
				<t id="no">Kokosøyene</t>
			</c>
			<c id="CD">
				<t id="en">Congo, the Democratic Republic of the</t>
				<t id="no">Den demokratiske republikken Kongo</t>
			</c>
			<c id="CF">
				<t id="en">Central African Republic</t>
				<t id="no">Den sentralafrikanske republikk</t>
			</c>
			<c id="CG">
				<t id="en">Congo</t>
				<t id="no">Republikken Kongo</t>
			</c>
			<c id="CH">
				<t id="en">Switzerland</t>
				<t id="no">Sveits</t>
			</c>
			<c id="CI">
				<t id="en">Côte d'Ivoire</t>
				<t id="no">Elfenbenskysten</t>
			</c>
			<c id="CK">
				<t id="en">Cook Islands</t>
				<t id="no">Cookøyene</t>
			</c>
			<c id="CL">
				<t id="en">Chile</t>
				<t id="no">Chile</t>
			</c>
			<c id="CM">
				<t id="en">Cameroon</t>
				<t id="no">Kamerun</t>
			</c>
			<c id="CN">
				<t id="en">China</t>
				<t id="no">Kina</t>
			</c>
			<c id="CO">
				<t id="en">Colombia</t>
				<t id="no">Colombia</t>
			</c>
			<c id="CR">
				<t id="en">Costa Rica</t>
				<t id="no">Costa Rica</t>
			</c>
			<c id="CU">
				<t id="en">Cuba</t>
				<t id="no">Cuba</t>
			</c>
			<c id="CV">
				<t id="en">Cabo Verde</t>
				<t id="no">Kapp Verde</t>
			</c>
			<c id="CW">
				<t id="en">Curaçao</t>
				<t id="no">Curaçao</t>
			</c>
			<c id="CX">
				<t id="en">Christmas Island</t>
				<t id="no">Christmasøya</t>
			</c>
			<c id="CY">
				<t id="en">Cyprus</t>
				<t id="no">Kypros</t>
			</c>
			<c id="CZ">
				<t id="en">Czechia</t>
				<t id="no">Tsjekkia</t>
			</c>
			<c id="DE">
				<t id="en">Germany</t>
				<t id="no">Tyskland</t>
			</c>
			<c id="DJ">
				<t id="en">Djibouti</t>
				<t id="no">Djibouti</t>
			</c>
			<c id="DK">
				<t id="en">Denmark</t>
				<t id="no">Danmark</t>
			</c>
			<c id="DM">
				<t id="en">Dominica</t>
				<t id="no">Dominica</t>
			</c>
			<c id="DO">
				<t id="en">Dominican Republic</t>
				<t id="no">Den dominikanske republikk</t>
			</c>
			<c id="DZ">
				<t id="en">Algeria</t>
				<t id="no">Algerie</t>
			</c>
			<c id="EC">
				<t id="en">Ecuador</t>
				<t id="no">Ecuador</t>
			</c>
			<c id="EE">
				<t id="en">Estonia</t>
				<t id="no">Estland</t>
			</c>
			<c id="EG">
				<t id="en">Egypt</t>
				<t id="no">Egypt</t>
			</c>
			<c id="EH">
				<t id="en">Western Sahara</t>
				<t id="no">Vest-Sahara</t>
			</c>
			<c id="ER">
				<t id="en">Eritrea</t>
				<t id="no">Eritrea</t>
			</c>
			<c id="ES">
				<t id="en">Spain</t>
				<t id="no">Spania</t>
			</c>
			<c id="ET">
				<t id="en">Ethiopia</t>
				<t id="no">Etiopia</t>
			</c>
			<c id="FI">
				<t id="en">Finland</t>
				<t id="no">Finland</t>
			</c>
			<c id="FJ">
				<t id="en">Fiji</t>
				<t id="no">Fiji</t>
			</c>
			<c id="FK">
				<t id="en">Falkland Islands (Malvinas)</t>
				<t id="no">Falklandsøyene</t>
			</c>
			<c id="FM">
				<t id="en">Micronesia, Federated States of</t>
				<t id="no">Mikronesiaføderasjonen</t>
			</c>
			<c id="FO">
				<t id="en">Faroe Islands</t>
				<t id="no">Færøyene</t>
			</c>
			<c id="FR">
				<t id="en">France</t>
				<t id="no">Frankrike</t>
			</c>
			<c id="GA">
				<t id="en">Gabon</t>
				<t id="no">Gabon</t>
			</c>
			<c id="GB">
				<t id="en">United Kingdom of Great Britain and Northern Ireland</t>
				<t id="no">Storbritannia</t>
			</c>
			<c id="GD">
				<t id="en">Grenada</t>
				<t id="no">Grenada</t>
			</c>
			<c id="GE">
				<t id="en">Georgia</t>
				<t id="no">Georgia</t>
			</c>
			<c id="GF">
				<t id="en">French Guiana</t>
				<t id="no">Fransk Guyana</t>
			</c>
			<c id="GG">
				<t id="en">Guernsey</t>
				<t id="no">Guernsey</t>
			</c>
			<c id="GH">
				<t id="en">Ghana</t>
				<t id="no">Ghana</t>
			</c>
			<c id="GI">
				<t id="en">Gibraltar</t>
				<t id="no">Gibraltar</t>
			</c>
			<c id="GL">
				<t id="en">Greenland</t>
				<t id="no">Grønland</t>
			</c>
			<c id="GM">
				<t id="en">Gambia</t>
				<t id="no">Gambia</t>
			</c>
			<c id="GN">
				<t id="en">Guinea</t>
				<t id="no">Guinea</t>
			</c>
			<c id="GP">
				<t id="en">Guadeloupe</t>
				<t id="no">Guadeloupe</t>
			</c>
			<c id="GQ">
				<t id="en">Equatorial Guinea</t>
				<t id="no">Ekvatorial-Guinea</t>
			</c>
			<c id="GR">
				<t id="en">Greece</t>
				<t id="no">Hellas</t>
			</c>
			<c id="GS">
				<t id="en">South Georgia and the South Sandwich Islands</t>
				<t id="no">Sør-Georgia og Sør-Sandwichøyene</t>
			</c>
			<c id="GT">
				<t id="en">Guatemala</t>
				<t id="no">Guatemala</t>
			</c>
			<c id="GU">
				<t id="en">Guam</t>
				<t id="no">Guam</t>
			</c>
			<c id="GW">
				<t id="en">Guinea-Bissau</t>
				<t id="no">Guinea-Bissau</t>
			</c>
			<c id="GY">
				<t id="en">Guyana</t>
				<t id="no">Guyana</t>
			</c>
			<c id="HK">
				<t id="en">Hong Kong</t>
				<t id="no">Hongkong</t>
			</c>
			<c id="HM">
				<t id="en">Heard Island and McDonald Islands</t>
				<t id="no">Heard- og McDonaldøyene</t>
			</c>
			<c id="HN">
				<t id="en">Honduras</t>
				<t id="no">Honduras</t>
			</c>
			<c id="HR">
				<t id="en">Croatia</t>
				<t id="no">Kroatia</t>
			</c>
			<c id="HT">
				<t id="en">Haiti</t>
				<t id="no">Haiti</t>
			</c>
			<c id="HU">
				<t id="en">Hungary</t>
				<t id="no">Ungarn</t>
			</c>
			<c id="ID">
				<t id="en">Indonesia</t>
				<t id="no">Indonesia</t>
			</c>
			<c id="IE">
				<t id="en">Ireland</t>
				<t id="no">Irland</t>
			</c>
			<c id="IL">
				<t id="en">Israel</t>
				<t id="no">Israel</t>
			</c>
			<c id="IM">
				<t id="en">Isle of Man</t>
				<t id="no">Man</t>
			</c>
			<c id="IN">
				<t id="en">India</t>
				<t id="no">India</t>
			</c>
			<c id="IO">
				<t id="en">British Indian Ocean Territory</t>
				<t id="no">Det britiske territoriet i Indiahavet</t>
			</c>
			<c id="IQ">
				<t id="en">Iraq</t>
				<t id="no">Irak</t>
			</c>
			<c id="IR">
				<t id="en">Iran, Islamic Republic of</t>
				<t id="no">Iran</t>
			</c>
			<c id="IS">
				<t id="en">Iceland</t>
				<t id="no">Island</t>
			</c>
			<c id="IT">
				<t id="en">Italy</t>
				<t id="no">Italia</t>
			</c>
			<c id="JE">
				<t id="en">Jersey</t>
				<t id="no">Jersey</t>
			</c>
			<c id="JM">
				<t id="en">Jamaica</t>
				<t id="no">Jamaica</t>
			</c>
			<c id="JO">
				<t id="en">Jordan</t>
				<t id="no">Jordan</t>
			</c>
			<c id="JP">
				<t id="en">Japan</t>
				<t id="no">Japan</t>
			</c>
			<c id="KE">
				<t id="en">Kenya</t>
				<t id="no">Kenya</t>
			</c>
			<c id="KG">
				<t id="en">Kyrgyzstan</t>
				<t id="no">Kirgisistan</t>
			</c>
			<c id="KH">
				<t id="en">Cambodia</t>
				<t id="no">Kambodsja</t>
			</c>
			<c id="KI">
				<t id="en">Kiribati</t>
				<t id="no">Kiribati</t>
			</c>
			<c id="KM">
				<t id="en">Comoros</t>
				<t id="no">Komorene</t>
			</c>
			<c id="KN">
				<t id="en">Saint Kitts and Nevis</t>
				<t id="no">Saint Kitts og Nevis</t>
			</c>
			<c id="KP">
				<t id="en">Korea, Democratic People's Republic of</t>
				<t id="no">Nord-Korea</t>
			</c>
			<c id="KR">
				<t id="en">Korea, Republic of</t>
				<t id="no">Sør-Korea</t>
			</c>
			<c id="KW">
				<t id="en">Kuwait</t>
				<t id="no">Kuwait</t>
			</c>
			<c id="KY">
				<t id="en">Cayman Islands</t>
				<t id="no">Caymanøyene</t>
			</c>
			<c id="KZ">
				<t id="en">Kazakhstan</t>
				<t id="no">Kasakhstan</t>
			</c>
			<c id="LA">
				<t id="en">Lao People's Democratic Republic</t>
				<t id="no">Laos</t>
			</c>
			<c id="LB">
				<t id="en">Lebanon</t>
				<t id="no">Libanon</t>
			</c>
			<c id="LC">
				<t id="en">Saint Lucia</t>
				<t id="no">Saint Lucia</t>
			</c>
			<c id="LI">
				<t id="en">Liechtenstein</t>
				<t id="no">Liechtenstein</t>
			</c>
			<c id="LK">
				<t id="en">Sri Lanka</t>
				<t id="no">Sri Lanka</t>
			</c>
			<c id="LR">
				<t id="en">Liberia</t>
				<t id="no">Liberia</t>
			</c>
			<c id="LS">
				<t id="en">Lesotho</t>
				<t id="no">Lesotho</t>
			</c>
			<c id="LT">
				<t id="en">Lithuania</t>
				<t id="no">Litauen</t>
			</c>
			<c id="LU">
				<t id="en">Luxembourg</t>
				<t id="no">Luxembourg</t>
			</c>
			<c id="LV">
				<t id="en">Latvia</t>
				<t id="no">Latvia</t>
			</c>
			<c id="LY">
				<t id="en">Libya</t>
				<t id="no">Libya</t>
			</c>
			<c id="MA">
				<t id="en">Morocco</t>
				<t id="no">Marokko</t>
			</c>
			<c id="MC">
				<t id="en">Monaco</t>
				<t id="no">Monaco</t>
			</c>
			<c id="MD">
				<t id="en">Moldova, Republic of</t>
				<t id="no">Moldova</t>
			</c>
			<c id="ME">
				<t id="en">Montenegro</t>
				<t id="no">Montenegro</t>
			</c>
			<c id="MF">
				<t id="en">Saint Martin (French part)</t>
				<t id="no">Saint-Martin</t>
			</c>
			<c id="MG">
				<t id="en">Madagascar</t>
				<t id="no">Madagaskar</t>
			</c>
			<c id="MH">
				<t id="en">Marshall Islands</t>
				<t id="no">Marshalløyene</t>
			</c>
			<c id="MK">
				<t id="en">Macedonia, the former Yugoslav Republic of</t>
				<t id="no">Makedonia</t>
			</c>
			<c id="ML">
				<t id="en">Mali</t>
				<t id="no">Mali</t>
			</c>
			<c id="MM">
				<t id="en">Myanmar</t>
				<t id="no">Myanmar</t>
			</c>
			<c id="MN">
				<t id="en">Mongolia</t>
				<t id="no">Mongolia</t>
			</c>
			<c id="MO">
				<t id="en">Macao</t>
				<t id="no">Macao</t>
			</c>
			<c id="MP">
				<t id="en">Northern Mariana Islands</t>
				<t id="no">Nord-Marianene</t>
			</c>
			<c id="MQ">
				<t id="en">Martinique</t>
				<t id="no">Martinique</t>
			</c>
			<c id="MR">
				<t id="en">Mauritania</t>
				<t id="no">Mauritania</t>
			</c>
			<c id="MS">
				<t id="en">Montserrat</t>
				<t id="no">Montserrat</t>
			</c>
			<c id="MT">
				<t id="en">Malta</t>
				<t id="no">Malta</t>
			</c>
			<c id="MU">
				<t id="en">Mauritius</t>
				<t id="no">Mauritius</t>
			</c>
			<c id="MV">
				<t id="en">Maldives</t>
				<t id="no">Maldivene</t>
			</c>
			<c id="MW">
				<t id="en">Malawi</t>
				<t id="no">Malawi</t>
			</c>
			<c id="MX">
				<t id="en">Mexico</t>
				<t id="no">Mexico</t>
			</c>
			<c id="MY">
				<t id="en">Malaysia</t>
				<t id="no">Malaysia</t>
			</c>
			<c id="MZ">
				<t id="en">Mozambique</t>
				<t id="no">Mosambik</t>
			</c>
			<c id="NA">
				<t id="en">Namibia</t>
				<t id="no">Namibia</t>
			</c>
			<c id="NC">
				<t id="en">New Caledonia</t>
				<t id="no">Ny-Caledonia</t>
			</c>
			<c id="NE">
				<t id="en">Niger</t>
				<t id="no">Niger</t>
			</c>
			<c id="NF">
				<t id="en">Norfolk Island</t>
				<t id="no">Norfolkøya</t>
			</c>
			<c id="NG">
				<t id="en">Nigeria</t>
				<t id="no">Nigeria</t>
			</c>
			<c id="NI">
				<t id="en">Nicaragua</t>
				<t id="no">Nicaragua</t>
			</c>
			<c id="NL">
				<t id="en">Netherlands</t>
				<t id="no">Nederland</t>
			</c>
			<c id="NO">
				<t id="en">Norway</t>
				<t id="no">Norge</t>
			</c>
			<c id="NP">
				<t id="en">Nepal</t>
				<t id="no">Nepal</t>
			</c>
			<c id="NR">
				<t id="en">Nauru</t>
				<t id="no">Nauru</t>
			</c>
			<c id="NU">
				<t id="en">Niue</t>
				<t id="no">Niue</t>
			</c>
			<c id="NZ">
				<t id="en">New Zealand</t>
				<t id="no">New Zealand</t>
			</c>
			<c id="OM">
				<t id="en">Oman</t>
				<t id="no">Oman</t>
			</c>
			<c id="PA">
				<t id="en">Panama</t>
				<t id="no">Panama</t>
			</c>
			<c id="PE">
				<t id="en">Peru</t>
				<t id="no">Peru</t>
			</c>
			<c id="PF">
				<t id="en">French Polynesia</t>
				<t id="no">Fransk Polynesia</t>
			</c>
			<c id="PG">
				<t id="en">Papua New Guinea</t>
				<t id="no">Papua Ny-Guinea</t>
			</c>
			<c id="PH">
				<t id="en">Philippines</t>
				<t id="no">Filippinene</t>
			</c>
			<c id="PK">
				<t id="en">Pakistan</t>
				<t id="no">Pakistan</t>
			</c>
			<c id="PL">
				<t id="en">Poland</t>
				<t id="no">Polen</t>
			</c>
			<c id="PM">
				<t id="en">Saint Pierre and Miquelon</t>
				<t id="no">Saint-Pierre og Miquelon</t>
			</c>
			<c id="PN">
				<t id="en">Pitcairn</t>
				<t id="no">Pitcairnøyene</t>
			</c>
			<c id="PR">
				<t id="en">Puerto Rico</t>
				<t id="no">Puerto Rico</t>
			</c>
			<c id="PS">
				<t id="en">Palestine, State of</t>
				<t id="no">Palestina</t>
			</c>
			<c id="PT">
				<t id="en">Portugal</t>
				<t id="no">Portugal</t>
			</c>
			<c id="PW">
				<t id="en">Palau</t>
				<t id="no">Palau</t>
			</c>
			<c id="PY">
				<t id="en">Paraguay</t>
				<t id="no">Paraguay</t>
			</c>
			<c id="QA">
				<t id="en">Qatar</t>
				<t id="no">Qatar</t>
			</c>
			<c id="RE">
				<t id="en">Réunion</t>
				<t id="no">Réunion</t>
			</c>
			<c id="RO">
				<t id="en">Romania</t>
				<t id="no">Romania</t>
			</c>
			<c id="RS">
				<t id="en">Serbia</t>
				<t id="no">Serbia</t>
			</c>
			<c id="RU">
				<t id="en">Russian Federation</t>
				<t id="no">Russland</t>
			</c>
			<c id="RW">
				<t id="en">Rwanda</t>
				<t id="no">Rwanda</t>
			</c>
			<c id="SA">
				<t id="en">Saudi Arabia</t>
				<t id="no">Saudi-Arabia</t>
			</c>
			<c id="SB">
				<t id="en">Solomon Islands</t>
				<t id="no">Salomonøyene</t>
			</c>
			<c id="SC">
				<t id="en">Seychelles</t>
				<t id="no">Seychellene</t>
			</c>
			<c id="SD">
				<t id="en">Sudan</t>
				<t id="no">Sudan</t>
			</c>
			<c id="SE">
				<t id="en">Sweden</t>
				<t id="no">Sverige</t>
			</c>
			<c id="SG">
				<t id="en">Singapore</t>
				<t id="no">Singapore</t>
			</c>
			<c id="SH">
				<t id="en">Saint Helena, Ascension and Tristan da Cunha</t>
				<t id="no">St. Helena, Ascension og Tristan da Cunha</t>
			</c>
			<c id="SI">
				<t id="en">Slovenia</t>
				<t id="no">Slovenia</t>
			</c>
			<c id="SJ">
				<t id="en">Svalbard and Jan Mayen</t>
				<t id="no">Svalbard og Jan Mayen</t>
			</c>
			<c id="SK">
				<t id="en">Slovakia</t>
				<t id="no">Slovakia</t>
			</c>
			<c id="SL">
				<t id="en">Sierra Leone</t>
				<t id="no">Sierra Leone</t>
			</c>
			<c id="SM">
				<t id="en">San Marino</t>
				<t id="no">San Marino</t>
			</c>
			<c id="SN">
				<t id="en">Senegal</t>
				<t id="no">Senegal</t>
			</c>
			<c id="SO">
				<t id="en">Somalia</t>
				<t id="no">Somalia</t>
			</c>
			<c id="SR">
				<t id="en">Suriname</t>
				<t id="no">Surinam</t>
			</c>
			<c id="SS">
				<t id="en">South Sudan</t>
				<t id="no">Sør-Sudan</t>
			</c>
			<c id="ST">
				<t id="en">Sao Tome and Principe</t>
				<t id="no">São Tomé og Príncipe</t>
			</c>
			<c id="SV">
				<t id="en">El Salvador</t>
				<t id="no">El Salvador</t>
			</c>
			<c id="SX">
				<t id="en">Sint Maarten (Dutch part)</t>
				<t id="no">Sint Maarten</t>
			</c>
			<c id="SY">
				<t id="en">Syrian Arab Republic</t>
				<t id="no">Syria</t>
			</c>
			<c id="SZ">
				<t id="en">Swaziland</t>
				<t id="no">Swaziland</t>
			</c>
			<c id="TC">
				<t id="en">Turks and Caicos Islands</t>
				<t id="no">Turks- og Caicosøyene</t>
			</c>
			<c id="TD">
				<t id="en">Chad</t>
				<t id="no">Tsjad</t>
			</c>
			<c id="TF">
				<t id="en">French Southern Territories</t>
				<t id="no">De franske sørterritorier</t>
			</c>
			<c id="TG">
				<t id="en">Togo</t>
				<t id="no">Togo</t>
			</c>
			<c id="TH">
				<t id="en">Thailand</t>
				<t id="no">Thailand</t>
			</c>
			<c id="TJ">
				<t id="en">Tajikistan</t>
				<t id="no">Tadsjikistan</t>
			</c>
			<c id="TK">
				<t id="en">Tokelau</t>
				<t id="no">Tokelau</t>
			</c>
			<c id="TL">
				<t id="en">Timor-Leste</t>
				<t id="no">Øst-Timor</t>
			</c>
			<c id="TM">
				<t id="en">Turkmenistan</t>
				<t id="no">Turkmenistan</t>
			</c>
			<c id="TN">
				<t id="en">Tunisia</t>
				<t id="no">Tunisia</t>
			</c>
			<c id="TO">
				<t id="en">Tonga</t>
				<t id="no">Tonga</t>
			</c>
			<c id="TR">
				<t id="en">Turkey</t>
				<t id="no">Tyrkia</t>
			</c>
			<c id="TT">
				<t id="en">Trinidad and Tobago</t>
				<t id="no">Trinidad og Tobago</t>
			</c>
			<c id="TV">
				<t id="en">Tuvalu</t>
				<t id="no">Tuvalu</t>
			</c>
			<c id="TW">
				<t id="en">Taiwan, Province of China</t>
				<t id="no">Taiwan</t>
			</c>
			<c id="TZ">
				<t id="en">Tanzania, United Republic of</t>
				<t id="no">Tanzania</t>
			</c>
			<c id="UA">
				<t id="en">Ukraine</t>
				<t id="no">Ukraina</t>
			</c>
			<c id="UG">
				<t id="en">Uganda</t>
				<t id="no">Uganda</t>
			</c>
			<c id="UM">
				<t id="en">United States Minor Outlying Islands</t>
				<t id="no">USAs ytre småøyer</t>
			</c>
			<c id="US">
				<t id="en">United States of America</t>
				<t id="no">USA</t>
			</c>
			<c id="UY">
				<t id="en">Uruguay</t>
				<t id="no">Uruguay</t>
			</c>
			<c id="UZ">
				<t id="en">Uzbekistan</t>
				<t id="no">Usbekistan</t>
			</c>
			<c id="VA">
				<t id="en">Holy See</t>
				<t id="no">Vatikanstaten</t>
			</c>
			<c id="VC">
				<t id="en">Saint Vincent and the Grenadines</t>
				<t id="no">Saint Vincent og Grenadinene</t>
			</c>
			<c id="VE">
				<t id="en">Venezuela, Bolivarian Republic of</t>
				<t id="no">Venezuela</t>
			</c>
			<c id="VG">
				<t id="en">Virgin Islands, British</t>
				<t id="no">De britiske Jomfruøyer</t>
			</c>
			<c id="VI">
				<t id="en">Virgin Islands, U.S.</t>
				<t id="no">De amerikanske Jomfruøyer</t>
			</c>
			<c id="VN">
				<t id="en">Viet Nam</t>
				<t id="no">Vietnam</t>
			</c>
			<c id="VU">
				<t id="en">Vanuatu</t>
				<t id="no">Vanuatu</t>
			</c>
			<c id="WF">
				<t id="en">Wallis and Futuna</t>
				<t id="no">Wallis og Futuna</t>
			</c>
			<c id="WS">
				<t id="en">Samoa</t>
				<t id="no">Samoa</t>
			</c>
			<c id="YE">
				<t id="en">Yemen</t>
				<t id="no">Jemen</t>
			</c>
			<c id="YT">
				<t id="en">Mayotte</t>
				<t id="no">Mayotte</t>
			</c>
			<c id="ZA">
				<t id="en">South Africa</t>
				<t id="no">Sør-Afrika</t>
			</c>
			<c id="ZM">
				<t id="en">Zambia</t>
				<t id="no">Zambia</t>
			</c>
			<c id="ZW">
				<t id="en">Zimbabwe</t>
				<t id="no">Zimbabwe</t>
			</c>
		</cl>
		<cl id="uncl1001invoice">
			<c id="380">
				<t id="en">Commercial invoice</t>
				<t id="no">Kommersiell faktura</t>
			</c>
			<c id="393">
				<t id="en">Factored invoice</t>
				<t id="no">Factored invoice</t>
			</c>
			<c id="82">
				<t id="en">Metered services invoice</t>
				<t id="no">Metered services invoice</t>
			</c>
			<c id="80">
				<t id="en">Debit note related to goods or services</t>
				<t id="no">Debit note related to goods or services</t>
			</c>
			<c id="84">
				<t id="en">Debit note related to financial adjustments</t>
				<t id="no">Debit note related to financial adjustments</t>
			</c>
			<c id="395">
				<t id="en">Consignment invoice</t>
				<t id="no">Consignment invoice</t>
			</c>
			<c id="575">
				<t id="en">Forwarder’s invoice</t>
				<t id="no">Forwarder’s invoice</t>
			</c>
			<c id="780">
				<t id="en">Freight invoice</t>
				<t id="no">Freight invoice</t>
			</c>
		</cl>
		<cl id="uncl1001-cn">
			<c id="81">
				<t id="en">Credit note related to goods or services</t>
				<t id="no">Credit note related to goods or services</t>
			</c>
			<c id="83">
				<t id="en">Credit note related to financial adjustments</t>
				<t id="no">Credit note related to financial adjustments</t>
			</c>
			<c id="381">
				<t id="en">Credit note</t>
				<t id="no">Credit note</t>
			</c>
			<c id="396">
				<t id="en">Factored credit note</t>
				<t id="no">Factored credit note</t>
			</c>
			<c id="532">
				<t id="en">Forwarder's credit note</t>
				<t id="no">Forwarder's credit note</t>
			</c>
		</cl>
	</xsl:variable>
	<xsl:function name="u:label">
		<xsl:param name="part"/>
		<xsl:param name="value"/>
		<xsl:choose>
			<xsl:when test="$labels/g[@id=$part]/f[@id=$value]/t[@id=$language]">
				<xsl:value-of select="$labels/g[@id=$part]/f[@id=$value]/t[@id=$language]/text()"/>
			</xsl:when>
			<xsl:otherwise>
				<span class="mtr">[label:<xsl:value-of select="$part"/>.<xsl:value-of select="$value"/>]</span>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:function>
	<xsl:variable name="labels">
		<g id="attachments">
			<f id="Attachments">
				<t id="en">Attachments</t>
				<t id="no">Vedlegg</t>
			</f>
			<f id="Download">
				<t id="en">Download</t>
				<t id="no">Last ned</t>
			</f>
			<f id="Embedded">
				<t id="en">Embedded</t>
				<t id="no">Innebygd</t>
			</f>
		</g>
		<g id="delivery">
			<f id="Address">
				<t id="en">Address</t>
				<t id="no">Adresse</t>
			</f>
			<f id="Delivery">
				<t id="en">Delivery</t>
				<t id="no">Leveringssted</t>
			</f>
			<f id="DeliveryDate">
				<t id="en">Date</t>
				<t id="no">Dato</t>
			</f>
			<f id="DeliveryID">
				<t id="en">Location</t>
				<t id="no">Identifikator</t>
			</f>
		</g>
		<g id="document">
			<f id="CreditNote">
				<t id="en">Credit Note</t>
				<t id="no">Kreditnota</t>
			</f>
			<f id="Invoice">
				<t id="en">Invoice</t>
				<t id="no">Faktura</t>
			</f>
		</g>
		<g id="item">
			<f id="BuyersItemIdentification">
				<t id="en">Buyers Item Identification</t>
				<t id="no">Kjøpers vareidentifikator</t>
			</f>
			<f id="CommodityClassification">
				<t id="en">Commodity Classification</t>
				<t id="no">Klassifisering</t>
			</f>
			<f id="OriginCountry">
				<t id="en">Origin Country</t>
				<t id="no">Opprinnelseland</t>
			</f>
			<f id="SellersItemIdentification">
				<t id="en">Sellers Item Identification</t>
				<t id="no">Selgers vareidentifikator</t>
			</f>
			<f id="StandardItemIdentification">
				<t id="en">Standard Item Identification</t>
				<t id="no">Registrert vareidentifikator</t>
			</f>
		</g>
		<g id="line">
			<f id="Allowance">
				<t id="en">Allowance</t>
				<t id="no">Rabatt</t>
			</f>
			<f id="AllowanceIncluded">
				<t id="en">Included allowance</t>
				<t id="no">Inkludert rabatt</t>
			</f>
			<f id="AllowanceTotalAmount">
				<t id="en">Allowance Total</t>
				<t id="no">Rabattotal</t>
			</f>
			<f id="Charge">
				<t id="en">Charge</t>
				<t id="no">Gebyr</t>
			</f>
			<f id="ChargeIncluded">
				<t id="en">Included charge</t>
				<t id="no">Inkludert gebyr</t>
			</f>
			<f id="ChargeTotalAmount">
				<t id="en">Charge Total</t>
				<t id="no">Gebyrtotal</t>
			</f>
			<f id="DocumentReference">
				<t id="en">Document Reference</t>
				<t id="no">Dokumentreferanse</t>
			</f>
			<f id="InvoicePeriod">
				<t id="en">Invoice Period</t>
				<t id="no">Fakturaperiode</t>
			</f>
			<f id="LineExtensionAmount">
				<t id="en">Line Total</t>
				<t id="no">Linjetotal</t>
			</f>
			<f id="OrderLineReference">
				<t id="en">Order Line</t>
				<t id="no">Ordrelinje</t>
			</f>
		</g>
		<g id="metadata">
			<f id="AccountingCost">
				<t id="en">Accounting Cost</t>
				<t id="no">Kontering</t>
			</f>
			<f id="BuyerReference">
				<t id="en">Buyer Reference</t>
				<t id="no">Kjøpers referanse</t>
			</f>
			<f id="ContractDocumentReference">
				<t id="en">Contract Reference</t>
				<t id="no">Kontraktsnummer</t>
			</f>
			<f id="DespatchDocumentReference">
				<t id="en">Despatch Reference</t>
				<t id="no">Pakkseddel</t>
			</f>
			<f id="DocumentCurrencyCode">
				<t id="en">Currency</t>
				<t id="no">Valuta</t>
			</f>
			<f id="DueDate">
				<t id="en">Due Date</t>
				<t id="no">Betalingsfrist</t>
			</f>
			<f id="ID">
				<t id="en">Identifier</t>
				<t id="no">Identifikator</t>
			</f>
			<f id="InvoiceDocumentReference">
				<t id="en">Preceding Invoice Reference</t>
				<t id="no">Fakturareferanse</t>
			</f>
			<f id="InvoicePeriod">
				<t id="en">Invoice Period</t>
				<t id="no">Fakturaperiode</t>
			</f>
			<f id="IssueDate">
				<t id="en">Issue Date</t>
				<t id="no">Utstedt</t>
			</f>
			<f id="Metadata">
				<t id="en">Metadata</t>
				<t id="no">Metadata</t>
			</f>
			<f id="ObjectIdentifier">
				<t id="en">Call for Tender/Lot</t>
				<t id="no">Objektidentifikator</t>
			</f>
			<f id="OrderReference">
				<t id="en">Order Reference</t>
				<t id="no">Ordrenummer</t>
			</f>
			<f id="OriginatorDocumentReference">
				<t id="en">Originator Reference</t>
				<t id="no">Kildeidentifikator</t>
			</f>
			<f id="ProjectReference">
				<t id="en">Project Reference</t>
				<t id="no">Prosjektreferanse</t>
			</f>
			<f id="ReceiptDocumentReference">
				<t id="en">Receipt Reference</t>
				<t id="no">Kvitteringsreferanse</t>
			</f>
			<f id="TaxCurrencyCode">
				<t id="en">Tax Currency</t>
				<t id="no">MVA-valuta</t>
			</f>
			<f id="TaxPointDate">
				<t id="en">Tax Date</t>
				<t id="no">MVA-dato</t>
			</f>
		</g>
		<g id="party">
			<f id="BankingReference">
				<t id="en">Banking Reference</t>
				<t id="no">Bankidentifikator</t>
			</f>
			<f id="Customer">
				<t id="en">Customer</t>
				<t id="no">Kunde</t>
			</f>
			<f id="EndpointID">
				<t id="en">Technical Address</t>
				<t id="no">Teknisk adresse</t>
			</f>
			<f id="PartyIdentification">
				<t id="en">Party Identification</t>
				<t id="no">Aktøridentifikator</t>
			</f>
			<f id="PartyTaxScheme">
				<t id="en">Tax Identification</t>
				<t id="no">MVA-identifikator</t>
			</f>
			<f id="Supplier">
				<t id="en">Supplier</t>
				<t id="no">Leverandør</t>
			</f>
		</g>
		<g id="payment">
			<f id="Payment">
				<t id="en">Payment</t>
				<t id="no">Betaling</t>
			</f>
		</g>
		<g id="tax">
			<f id="Category">
				<t id="en">Category</t>
				<t id="no">Kategori</t>
			</f>
			<f id="Tax">
				<t id="en">Tax</t>
				<t id="no">Merverdiavgift</t>
			</f>
			<f id="TaxableAmount">
				<t id="en">Taxable</t>
				<t id="no">Grunnlag</t>
			</f>
			<f id="TaxAmount">
				<t id="en">Tax</t>
				<t id="no">MVA</t>
			</f>
			<f id="Total">
				<t id="en">Total</t>
				<t id="no">Total</t>
			</f>
		</g>
		<g id="total">
			<f id="PayableAmount">
				<t id="en">Payable</t>
				<t id="no">Payable</t>
			</f>
			<f id="PayableRoundingAmount">
				<t id="en">Rounding Amount</t>
				<t id="no">Avrunding</t>
			</f>
			<f id="PrepaidAmount">
				<t id="en">Prepaid</t>
				<t id="no">Forhåndsbetalt</t>
			</f>
			<f id="TaxExclusiveAmount">
				<t id="en">Tax Exclusive</t>
				<t id="no">Før MVA</t>
			</f>
			<f id="TaxInclusiveAmount">
				<t id="en">Tax Inclusive</t>
				<t id="no">Etter MVA</t>
			</f>
			<f id="Totals">
				<t id="en">Totals</t>
				<t id="no">Totaler</t>
			</f>
		</g>
	</xsl:variable>
	<xsl:param name="language" select="'en'"/>
	<xsl:function name="u:lang">
		<xsl:value-of select="$language"/>
	</xsl:function>
	<xsl:template name="mode_document">
		<xsl:apply-templates select="*" mode="document"/>
	</xsl:template>
	<xsl:param name="mode" select="'document'"/>
	<xsl:template match="/">
		<xsl:choose>
			<xsl:when test="$mode = 'document'">
				<xsl:call-template name="mode_document"/>
			</xsl:when>
			<xsl:when test="$mode = 'help'">
				<xsl:call-template name="mode_help"/>
			</xsl:when>
			<xsl:when test="$mode = 'translation'">
				<xsl:call-template name="mode_translation"/>
			</xsl:when>
			<xsl:otherwise>
				<xsl:call-template name="mode_unknown"/>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>
	<xsl:template name="mode_help">
		<html lang="en">
			<head>
				<xsl:call-template name="doc-head"/>
				<title>Help</title>
			</head>
			<body>
				<div id="document">
					<h1>Help</h1>
					<h2 id="supported-modes">Supported modes</h2>
					<ul>
						<li>
							<code>document</code> - Parsing of business document. (Default)</li>
						<li>
							<code>help</code> - Viewing this page.</li>
						<li>
							<code>translation</code> - List of all translatable strings.</li>
					</ul>
					<h2 id="parameters">Parameters</h2>
					<dl>
						<dt>download_attachment</dt>
						<dd>Default value: <code>false</code>
						</dd>
						<dt>language</dt>
						<dd>Default value: <code>en</code>
						</dd>
						<dt>mode</dt>
						<dd>See <a href="#supported-modes">supported modes for usage</a>. Default value: <code>document</code>
						</dd>
						<dt>stylesheet_url</dt>
						<dd>Set this parameter to provide location for stylesheet. This parameter removes the embedded stylesheet. Default value: <code>NONE</code>
						</dd>
					</dl>
				</div>
			</body>
		</html>
	</xsl:template>
	<xsl:template name="mode_translation">
		<html lang="en">
			<head>
				<xsl:call-template name="doc-head"/>
				<title>Translation</title>
			</head>
			<body>
				<div id="document">
					<h1>Translation</h1>
					<p>Use parameter <code>language</code> to change language of presented translation.</p>
					<h2>Labels</h2>
					<ul class="list-unstyled">
						<xsl:for-each select="$labels/g">
							<xsl:variable name="g" select="@id"/>
							<xsl:for-each select="f">
								<li>
									<xsl:value-of select="$g"/>.<xsl:value-of select="@id"/> = <xsl:copy-of select="u:label($g, @id)"/>
								</li>
							</xsl:for-each>
						</xsl:for-each>
					</ul>
					<xsl:for-each select="$codelists/cl">
						<xsl:variable name="cl" select="@id"/>
						<h2>Codelist: <xsl:value-of select="@id"/>
						</h2>
						<ul class="list-unstyled">
							<xsl:for-each select="c">
								<li>
									<xsl:value-of select="@id"/> = <xsl:copy-of select="u:codelist($cl, @id)"/>
								</li>
							</xsl:for-each>
						</ul>
					</xsl:for-each>
				</div>
			</body>
		</html>
	</xsl:template>
	<xsl:template name="mode_unknown">
		<html lang="en">
			<head>
				<xsl:call-template name="doc-head"/>
				<title>Unknown mode</title>
			</head>
			<body>
				<div id="document">
					<h1>Unknown mode</h1>
					<p type="lead">Provided mode <code>
							<xsl:value-of select="$mode"/>
						</code> is unknown. Please use mode <code>help</code> for more information.</p>
				</div>
			</body>
		</html>
	</xsl:template>
	<xsl:param name="download_attachment" select="'false'"/>
	<xsl:template name="attachments-block">
		<h3>
			<xsl:copy-of select="u:label('attachments', 'Attachments')"/>
		</h3>
		<xsl:choose>
			<xsl:when test="cac:AdditionalDocumentReference[cac:Attachment]">
				<ul>
					<xsl:apply-templates select="cac:AdditionalDocumentReference[cac:Attachment]" mode="attachment"/>
				</ul>
			</xsl:when>
			<xsl:otherwise>
				<em>No attachments provided.</em>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>
	<xsl:template match="cac:AdditionalDocumentReference[cac:Attachment]" mode="attachment">
		<li>
			<xsl:value-of select="cbc:ID"/>
			<xsl:if test="cbc:DocumentDescription">
				<br/>
				<small>
					<xsl:value-of select="cbc:DocumentDescription"/>
				</small>
			</xsl:if>
			<br/>
			<xsl:apply-templates select="cac:Attachment/*" mode="attachment"/>
		</li>
	</xsl:template>
	<xsl:template match="cac:ExternalReference" mode="attachment">
		<small>
			<a href="{cbc:URI}">
				<xsl:value-of select="cbc:URI"/>
			</a>
		</small>
	</xsl:template>
	<xsl:template match="cbc:EmbeddedDocumentBinaryObject" mode="attachment">
		<xsl:choose>
			<xsl:when test="xs:boolean($download_attachment) = true()">
				<small>
					<a href="data:{@mimeCode};base64,{replace(text(), '\s', '')}" download="{@filename}">
						<xsl:copy-of select="u:label('attachments', 'Download')"/> <xsl:value-of select="@filename"/>
					</a> (<xsl:value-of select="@mimeCode"/>)</small>
			</xsl:when>
			<xsl:otherwise>
				<small>
					<xsl:copy-of select="u:label('attachments', 'Embedded')"/>: <xsl:value-of select="@filename"/> (<xsl:value-of select="@mimeCode"/>)</small>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>
	<xsl:template match="*" mode="attachment"/>
	<xsl:template match="cbc:ID" mode="common">
		<small>
			<xsl:value-of select="@schemeID"/>:</small>
		<xsl:value-of select="text()"/>
	</xsl:template>
	<xsl:template match="cbc:*[ends-with(local-name(), 'Amount')]" mode="common">
		<xsl:value-of select="format-number(text(), '###,##0.00')"/> <small>
			<xsl:value-of select="@currencyID"/>
		</small>
	</xsl:template>
	<xsl:template match="cac:*[ends-with(local-name(), 'Period')]" mode="common">
		<xsl:apply-templates select="cbc:StartDate" mode="common"/> - <xsl:apply-templates select="cbc:EndDate" mode="common"/>
	</xsl:template>
	<xsl:template match="cac:*[ends-with(local-name(), 'Date')]" mode="common">
		<xsl:value-of select="text()"/>
	</xsl:template>
	<xsl:template match="cac:*[ends-with(local-name(), 'TaxCategory')]" mode="common">
		<small>
			<xsl:value-of select="cac:TaxScheme/cbc:ID"/>:</small>
		<xsl:value-of select="cbc:ID"/>
		<small> (<xsl:value-of select="cbc:Percent"/>%)</small>
		<xsl:if test="cbc:TaxExemptionReason">
			<br/>
			<small>
				<xsl:value-of select="cbc:TaxExemptionReason"/>
			</small>
		</xsl:if>
	</xsl:template>
	<xsl:template match="cbc:*[ends-with(local-name(), 'Quantity')]" mode="common">
		<xsl:value-of select="text()"/> <small>
			<xsl:value-of select="@unitCode"/>
		</small>
	</xsl:template>
	<xsl:template match="cbc:Note" mode="common">
		<p class="note">
			<xsl:for-each select="tokenize(text(), '\n')">
				<xsl:value-of select="normalize-space(.)"/>
				<xsl:if test="position() != last()">
					<br/>
				</xsl:if>
			</xsl:for-each>
		</p>
	</xsl:template>
	<xsl:template name="delivery-block">
		<h3>
			<xsl:copy-of select="u:label('delivery', 'Delivery')"/>
		</h3>
		<xsl:apply-templates select="cac:Delivery" mode="delivery"/>
	</xsl:template>
	<xsl:template match="cac:Delivery" mode="delivery">
		<div style="margin-bottom: 10pt;">
			<dl>
				<xsl:apply-templates select="cbc:ActualDeliveryDate" mode="delivery"/>
				<xsl:apply-templates select="cac:DeliveryLocation/cbc:ID" mode="delivery"/>
				<xsl:apply-templates select="cac:DeliveryLocation/cac:Address" mode="delivery"/>
			</dl>
		</div>
	</xsl:template>
	<xsl:template match="cbc:ActualDeliveryDate" mode="delivery">
		<dt>
			<xsl:copy-of select="u:label('delivery', 'DeliveryDate')"/>
		</dt>
		<dd>
			<xsl:value-of select="text()"/>
		</dd>
	</xsl:template>
	<xsl:template match="cbc:ID" mode="delivery">
		<dt>
			<xsl:copy-of select="u:label('delivery', 'DeliveryID')"/>
		</dt>
		<dd>
			<xsl:apply-templates select="current()" mode="common"/>
		</dd>
	</xsl:template>
	<xsl:template match="cac:Address" mode="delivery">
		<dt>
			<xsl:copy-of select="u:label('delivery', 'Address')"/>
		</dt>
		<dd>
			<div>
				<xsl:value-of select="cbc:StreetName"/>
			</div>
			<div>
				<xsl:value-of select="cbc:AdditionalStreetName"/>
			</div>
			<div>
				<span>
					<xsl:value-of select="cbc:PostalZone"/>
				</span>
				<span> </span>
				<span>
					<xsl:value-of select="cbc:CityName"/>
				</span>
			</div>
			<div>
				<xsl:value-of select="cbc:CountrySubentity"/>
			</div>
			<div>
				<xsl:copy-of select="u:codelist('iso3166', cac:Country/cbc:IdentificationCode/text())"/>
			</div>
		</dd>
	</xsl:template>
	<xsl:template match="cac:Item" mode="item-info">
		<xsl:apply-templates select="cbc:Name" mode="item-info"/>
		<xsl:apply-templates select="cbc:Description" mode="item-info"/>
	</xsl:template>
	<xsl:template match="cbc:Name" mode="item-info">
		<div>
			<strong>
				<xsl:value-of select="text()"/>
			</strong>
		</div>
	</xsl:template>
	<xsl:template match="cbc:Description" mode="item-info">
		<div>
			<xsl:value-of select="text()"/>
		</div>
	</xsl:template>
	<xsl:template match="cac:Item" mode="item-details">
		<xsl:apply-templates select="*" mode="item-details"/>
	</xsl:template>
	<xsl:template match="cac:BuyersItemIdentification" mode="item-details">
		<dt>
			<xsl:copy-of select="u:label('item', local-name())"/>
		</dt>
		<dd>
			<xsl:value-of select="cbc:ID"/>
		</dd>
	</xsl:template>
	<xsl:template match="cac:SellersItemIdentification" mode="item-details">
		<dt>
			<xsl:copy-of select="u:label('item', local-name())"/>
		</dt>
		<dd>
			<xsl:value-of select="cbc:ID"/>
		</dd>
	</xsl:template>
	<xsl:template match="cac:StandardItemIdentification" mode="item-details">
		<dt>
			<xsl:copy-of select="u:label('item', local-name())"/>
		</dt>
		<dd>
			<xsl:apply-templates select="cbc:ID" mode="common"/>
		</dd>
	</xsl:template>
	<xsl:template match="cac:CommodityClassification" mode="item-details">
		<dt>
			<xsl:copy-of select="u:label('item', local-name())"/>
		</dt>
		<dd>
			<small>
				<xsl:value-of select="cbc:ItemClassificationCode/@listID"/>:</small>
			<xsl:value-of select="cbc:ItemClassificationCode"/>
		</dd>
	</xsl:template>
	<xsl:template match="cac:OriginCountry" mode="item-details">
		<dt>
			<xsl:copy-of select="u:label('item', local-name())"/>
		</dt>
		<dd>
			<xsl:copy-of select="u:codelist('iso3166', cbc:IdentificationCode/text())"/>
		</dd>
	</xsl:template>
	<xsl:template match="cac:ClassifiedTaxCategory | cbc:Name | cbc:Description | cac:AdditionalItemProperty" mode="item-details"/>
	<xsl:template match="*" mode="item-details">
		<div>[<xsl:value-of select="local-name()"/>]</div>
	</xsl:template>
	<xsl:template match="cac:AdditionalItemProperty" mode="item-properties">
		<dt>
			<xsl:value-of select="cbc:Name"/>
		</dt>
		<dd>
			<xsl:value-of select="cbc:Value"/>
		</dd>
	</xsl:template>
	<xsl:template match="cac:AllowanceCharge[cbc:ChargeIndicator='false']" mode="line">
		<div class="row">
			<div class="col-sm-11 col-sm-offset-1">
				<xsl:value-of select="cbc:AllowanceChargeReason"/>
				<xsl:if test="cbc:AllowanceChargeReasonCode"> <small>(<xsl:value-of select="cbc:AllowanceChargeReasonCode"/>)</small>
				</xsl:if>
			</div>
		</div>
		<div class="linetotal">
			<div class="row">
				<div class="col-sm-7 col-sm-offset-1">
					<xsl:copy-of select="u:label('line', 'Allowance')"/>
				</div>
				<div class="col-sm-2">
					<xsl:apply-templates select="cac:TaxCategory" mode="common"/>
				</div>
				<div class="col-sm-2 text-right">-<xsl:apply-templates select="cbc:Amount" mode="common"/>
				</div>
			</div>
		</div>
	</xsl:template>
	<xsl:template match="cac:AllowanceCharge[cbc:ChargeIndicator='true']" mode="line">
		<div class="row">
			<div class="col-sm-11 col-sm-offset-1">
				<xsl:value-of select="cbc:AllowanceChargeReason"/>
				<xsl:if test="cbc:AllowanceChargeReasonCode"> <small>(<xsl:value-of select="cbc:AllowanceChargeReasonCode"/>)</small>
				</xsl:if>
			</div>
		</div>
		<div class="linetotal">
			<div class="row">
				<div class="col-sm-7 col-sm-offset-1">
					<xsl:copy-of select="u:label('line', 'Charge')"/>
				</div>
				<div class="col-sm-2">
					<xsl:apply-templates select="cac:TaxCategory" mode="common"/>
				</div>
				<div class="col-sm-2 text-right">
					<xsl:apply-templates select="cbc:Amount" mode="common"/>
				</div>
			</div>
		</div>
	</xsl:template>
	<xsl:template match="cac:InvoiceLine | cac:CreditNoteLine" mode="line">
		<div class="line">
			<div class="number">
				<xsl:value-of select="cbc:ID"/>
			</div>
			<div class="col-sm-10">
				<div class="row">
					<div class="info col-sm-6">
						<xsl:apply-templates select="cac:Item" mode="item-info"/>
						<xsl:apply-templates select="cbc:Note" mode="common"/>
						<xsl:if test="cac:InvoicePeriod | cac:OrderLineReference | cac:DocumentReference">
							<dl>
								<xsl:apply-templates select="cac:InvoicePeriod" mode="line"/>
								<xsl:if test="normalize-space(cac:OrderLineReference/cbc:LineID/text())">
									<dt>
										<xsl:copy-of select="u:label('line', 'OrderLineReference')"/>
									</dt>
									<dd>
										<xsl:value-of select="cac:OrderLineReference/cbc:LineID"/>
									</dd>
								</xsl:if>
								<xsl:apply-templates select="cac:DocumentReference" mode="line"/>
							</dl>
						</xsl:if>
					</div>
					<div class="details col-sm-6">
						<xsl:if test="cac:Item/cac:AdditionalItemProperty">
							<dl>
								<xsl:apply-templates select="cac:Item/cac:AdditionalItemProperty" mode="item-properties"/>
							</dl>
						</xsl:if>
						<dl>
							<xsl:apply-templates select="cac:Item" mode="item-details"/>
						</dl>
					</div>
				</div>
				<xsl:apply-templates select="cac:Price" mode="line"/>
				<xsl:apply-templates select="cac:AllowanceCharge" mode="line-ac"/>
			</div>
		</div>
		<div class="linetotal">
			<div class="row">
				<div class="col-sm-3 col-sm-offset-1">
					<xsl:apply-templates select="cbc:InvoicedQuantity" mode="common"/>
				</div>
				<div class="col-sm-4">
					<xsl:value-of select="cbc:AccountingCost"/>
				</div>
				<div class="col-sm-2">
					<xsl:apply-templates select="cac:Item/cac:ClassifiedTaxCategory" mode="common"/>
				</div>
				<div class="col-sm-2 text-right">
					<xsl:apply-templates select="cbc:LineExtensionAmount" mode="common"/>
				</div>
			</div>
		</div>
	</xsl:template>
	<xsl:template match="cac:InvoicePeriod" mode="line">
		<dt>
			<xsl:copy-of select="u:label('line', local-name())"/>
		</dt>
		<dd>
			<xsl:apply-templates select="current()" mode="common"/>
		</dd>
	</xsl:template>
	<xsl:template match="cac:DocumentReference" mode="line">
		<dt>
			<xsl:copy-of select="u:label('line', local-name())"/>
		</dt>
		<dd>
			<xsl:apply-templates select="cbc:ID" mode="common"/>
		</dd>
	</xsl:template>
	<xsl:template match="cac:Price" mode="line">
		<div class="linesupport">
			<div class="row">
				<div class="col-sm-9">Price á <xsl:apply-templates select="cbc:BaseQuantity" mode="common"/>
				</div>
				<div class="col-sm-3 text-right">
					<xsl:apply-templates select="cbc:PriceAmount" mode="common"/>
				</div>
			</div>
		</div>
	</xsl:template>
	<xsl:template match="cac:AllowanceCharge[cbc:ChargeIndicator='true']" mode="line-ac">
		<div class="linesupport">
			<div class="row">
				<div class="col-sm-9">
					<xsl:copy-of select="u:label('line', 'ChargeIncluded')"/>: <xsl:value-of select="cbc:AllowanceChargeReason"/>
				</div>
				<div class="col-sm-3 text-right">
					<xsl:apply-templates select="cbc:Amount" mode="common"/>
				</div>
			</div>
		</div>
	</xsl:template>
	<xsl:template match="cac:AllowanceCharge[cbc:ChargeIndicator='false']" mode="line-ac">
		<div class="linesupport">
			<div class="row">
				<div class="col-sm-9">
					<xsl:copy-of select="u:label('line', 'AllowanceIncluded')"/>: <xsl:value-of select="cbc:AllowanceChargeReason"/>
				</div>
				<div class="col-sm-3 text-right">-<xsl:apply-templates select="cbc:Amount" mode="common"/>
				</div>
			</div>
		</div>
	</xsl:template>
	<xsl:template match="cbc:LineExtensionAmount | cbc:ChargeTotalAmount" mode="line">
		<div class="total">
			<div class="row">
				<div class="col-xs-6 col-sm-8 col-sm-offset-1">
					<xsl:copy-of select="u:label('line', local-name())"/>
				</div>
				<div class="col-xs-6 col-sm-2 col-sm-offset-1 text-right">
					<xsl:apply-templates select="current()" mode="common"/>
				</div>
			</div>
		</div>
	</xsl:template>
	<xsl:template match="cbc:AllowanceTotalAmount" mode="line">
		<div class="total">
			<div class="row">
				<div class="col-xs-6 col-sm-8 col-sm-offset-1">
					<xsl:copy-of select="u:label('line', local-name())"/>
				</div>
				<div class="col-xs-6 col-sm-2 col-sm-offset-1 text-right">-<xsl:apply-templates select="current()" mode="common"/>
				</div>
			</div>
		</div>
	</xsl:template>
	<xsl:template match="*" mode="line">
		<div>[<xsl:value-of select="local-name()"/>]</div>
	</xsl:template>
	<xsl:template name="metadata">
		<h3>
			<xsl:copy-of select="u:label('metadata', 'Metadata')"/>
		</h3>
		<dl>
			<xsl:apply-templates select="cbc:ID" mode="metadata-detail"/>
			<xsl:apply-templates select="cbc:BuyerReference" mode="metadata-detail"/>
			<xsl:apply-templates select="cac:ProjectReference" mode="metadata-detail"/>
			<xsl:apply-templates select="cbc:IssueDate" mode="metadata-detail"/>
			<xsl:apply-templates select="cbc:DueDate" mode="metadata-detail"/>
			<xsl:apply-templates select="cbc:TaxPointDate" mode="metadata-detail"/>
			<xsl:apply-templates select="cac:InvoicePeriod" mode="metadata-detail"/>
			<xsl:apply-templates select="cbc:AccountingCost" mode="metadata-detail"/>
			<xsl:apply-templates select="cac:OrderReference" mode="metadata-detail"/>
			<xsl:apply-templates select="cac:BillingReference" mode="metadata-detail-exact"/>
			<xsl:apply-templates select="cac:DespatchDocumentReference" mode="metadata-detail"/>
			<xsl:apply-templates select="cac:ReceiptDocumentReference" mode="metadata-detail"/>
			<xsl:apply-templates select="cac:OriginatorDocumentReference" mode="metadata-detail"/>
			<xsl:apply-templates select="cac:ContractDocumentReference" mode="metadata-detail"/>
			<xsl:apply-templates select="cac:AdditionalDocumentReference[cbc:ID[@schemeID]]" mode="metadata-detail-exact"/>
			<xsl:apply-templates select="cbc:DocumentCurrencyCode" mode="metadata-detail"/>
			<xsl:apply-templates select="cbc:TaxCurrencyCode" mode="metadata-detail"/>
		</dl>
	</xsl:template>
	<xsl:template match="cac:BillingReference" mode="metadata-detail-exact">
		<xsl:for-each select="cac:InvoiceDocumentReference">
			<xsl:apply-templates select="." mode="metadata-detail"/>
		</xsl:for-each>
	</xsl:template>
	<xsl:template match="cac:AdditionalDocumentReference" mode="metadata-detail-exact">
		<dt>
			<xsl:copy-of select="u:label('metadata', 'ObjectIdentifier')"/>
		</dt>
		<dd>
			<small>
				<xsl:value-of select="cbc:ID/@schemeID"/>:</small>
			<xsl:value-of select="cbc:ID"/>
		</dd>
	</xsl:template>
	<xsl:template match="cac:*[ends-with(local-name(), 'Period')]" mode="metadata-detail">
		<dt>
			<xsl:copy-of select="u:label('metadata', local-name())"/>
		</dt>
		<dd>
			<xsl:apply-templates select="current()" mode="common"/>
			<xsl:if test="cbc:DescriptionCode">
				<span> (<xsl:value-of select="cbc:DescriptionCode"/>)</span>
			</xsl:if>
		</dd>
	</xsl:template>
	<xsl:template match="cac:*[ends-with(local-name(), 'Reference')]" mode="metadata-detail">
		<dt>
			<xsl:copy-of select="u:label('metadata', local-name())"/>
		</dt>
		<dd>
			<xsl:value-of select="cbc:ID"/>
			<xsl:if test="cbc:IssueDate">
				<span> (<xsl:value-of select="cbc:IssueDate"/>)</span>
			</xsl:if>
		</dd>
	</xsl:template>
	<xsl:template match="cbc:*" mode="metadata-detail">
		<dt>
			<xsl:copy-of select="u:label('metadata', local-name())"/>
		</dt>
		<dd>
			<xsl:value-of select="text()"/>
		</dd>
	</xsl:template>
	<xsl:template match="cac:*" mode="metadata-detail">
		<dt>
			<xsl:copy-of select="u:label('metadata', local-name())"/>
		</dt>
		<dd>
			<xsl:value-of select="current()"/>
		</dd>
	</xsl:template>
	<xsl:template match="cac:Party | cac:PayeeParty | cac:TaxRepresentativeParty" mode="party-with-contact">
		<div class="row">
			<div class="col-md-7">
				<xsl:if test="cac:PartyName | cac:PostalAddress">
					<div style="margin-bottom: 10pt;">
						<xsl:apply-templates select="cac:PartyName" mode="party"/>
						<xsl:apply-templates select="cac:PostalAddress" mode="party"/>
					</div>
				</xsl:if>
				<xsl:if test="cac:PartyLegalEntity">
					<div style="margin-bottom: 10pt;">
						<xsl:apply-templates select="cac:PartyLegalEntity" mode="party"/>
					</div>
				</xsl:if>
			</div>
			<div class="col-md-5">
				<xsl:apply-templates select="cac:Contact" mode="party"/>
			</div>
		</div>
		<xsl:if test="cbc:EndpointID | cac:PartyLegalEntity/cbc:CompanyID | cac:PartyIdentification | cac:PartyTaxScheme">
			<dl style="margin-bottom: 10pt;">
				<xsl:apply-templates select="cac:PartyIdentification[cbc:ID[@schemeID]]" mode="party"/>
				<xsl:apply-templates select="cac:PartyIdentification[cbc:ID[not(@schemeID)]]" mode="party"/>
				<xsl:apply-templates select="cac:PartyTaxScheme" mode="party"/>
				<xsl:apply-templates select="cbc:EndpointID" mode="party"/>
			</dl>
		</xsl:if>
	</xsl:template>
	<xsl:template match="cac:Party | cac:PayeeParty | cac:TaxRepresentativeParty" mode="party">
		<xsl:if test="cac:PartyName | cac:PostalAddress">
			<div style="margin-bottom: 10pt;">
				<xsl:apply-templates select="cac:PartyName" mode="party"/>
				<xsl:apply-templates select="cac:PartyLegalEntity/cbc:RegistrationName" mode="party"/>
				<xsl:apply-templates select="cac:PostalAddress" mode="party"/>
			</div>
		</xsl:if>
		<xsl:if test="cbc:EndpointID | cac:PartyIdentification | cac:PartyTaxScheme">
			<dl style="margin-bottom: 10pt;">
				<xsl:apply-templates select="cac:PartyLegalEntity/cbc:CompanyID" mode="party"/>
				<xsl:apply-templates select="cac:PartyIdentification[cbc:ID[@schemeID]]" mode="party"/>
				<xsl:apply-templates select="cac:PartyIdentification[cbc:ID[not(@schemeID)]]" mode="party"/>
				<xsl:apply-templates select="cac:PartyTaxScheme" mode="party"/>
				<xsl:apply-templates select="cbc:EndpointID" mode="party"/>
			</dl>
		</xsl:if>
	</xsl:template>
	<xsl:template match="cac:PartyName" mode="party">
		<div>
			<strong>
				<xsl:value-of select="cbc:Name"/>
			</strong>
		</div>
	</xsl:template>
	<xsl:template match="cac:PostalAddress" mode="party">
		<xsl:if test="cbc:StreetName">
			<div>
				<xsl:value-of select="cbc:StreetName"/>
			</div>
		</xsl:if>
		<xsl:if test="cbc:AdditionalStreetName">
			<div>
				<xsl:value-of select="cbc:AdditionalStreetName"/>
			</div>
		</xsl:if>
		<xsl:if test="cac:AddressLine">
			<xsl:for-each select="cac:AddressLine">
				<div>
					<xsl:value-of select="cbc:Line"/>
				</div>
			</xsl:for-each>
		</xsl:if>
		<xsl:if test="cbc:PostalZone and cbc:CityName">
			<div>
				<span>
					<xsl:value-of select="cbc:PostalZone"/>
				</span>
				<span> </span>
				<span>
					<xsl:value-of select="cbc:CityName"/>
				</span>
			</div>
		</xsl:if>
		<xsl:if test="cbc:CountrySubentity">
			<div>
				<xsl:value-of select="cbc:CountrySubentity"/>
			</div>
		</xsl:if>
		<xsl:if test="cac:Country">
			<div>
				<xsl:copy-of select="u:codelist('iso3166', cac:Country/cbc:IdentificationCode/text())"/>
			</div>
		</xsl:if>
	</xsl:template>
	<xsl:template match="cac:PartyIdentification[cbc:ID[@schemeID]]" mode="party">
		<dt>
			<xsl:copy-of select="u:label('party', local-name())"/>
		</dt>
		<dd>
			<xsl:apply-templates select="cbc:ID" mode="common"/>
		</dd>
	</xsl:template>
	<xsl:template match="cac:PartyIdentification[cbc:ID[not(@schemeID)]]" mode="party">
		<dt>
			<xsl:copy-of select="u:label('party', 'BankingReference')"/>
		</dt>
		<dd>
			<xsl:value-of select="cbc:ID"/>
		</dd>
	</xsl:template>
	<xsl:template match="cac:PartyLegalEntity" mode="party">
		<div>
			<strong>
				<xsl:value-of select="cbc:RegistrationName"/>
			</strong> (<xsl:value-of select="cbc:CompanyLegalForm"/>)</div>
		<div>
			<small>
				<xsl:value-of select="cbc:CompanyID/@schemeID"/>:</small>
			<xsl:value-of select="cbc:CompanyID"/>
		</div>
	</xsl:template>
	<xsl:template match="cac:PartyLegalEntity/cbc:CompanyID" mode="party">
		<dt>Legal Company Identifier</dt>
		<dd>
			<xsl:value-of select="text()"/>
		</dd>
	</xsl:template>
	<xsl:template match="cac:PartyTaxScheme" mode="party">
		<dt>
			<xsl:copy-of select="u:label('party', local-name())"/>
		</dt>
		<dd>
			<small>
				<xsl:value-of select="cac:TaxScheme/cbc:ID"/>:</small>
			<xsl:value-of select="cbc:CompanyID"/>
		</dd>
	</xsl:template>
	<xsl:template match="cbc:EndpointID" mode="party">
		<dt>
			<xsl:copy-of select="u:label('party', local-name())"/>
		</dt>
		<dd>
			<small>
				<xsl:value-of select="@schemeID"/>:</small>
			<xsl:value-of select="text()"/>
		</dd>
	</xsl:template>
	<xsl:template match="cac:Contact" mode="party">
		<div style="margin-bottom: 10pt;">
			<div>
				<xsl:value-of select="cbc:Name"/>
			</div>
			<div>tlf: <xsl:value-of select="cbc:Telephone"/>
			</div>
			<div>
				<a href="mailto:{cbc:ElectronicMail}">
					<xsl:value-of select="cbc:ElectronicMail"/>
				</a>
			</div>
		</div>
	</xsl:template>
	<xsl:template match="cac:PaymentMeans" mode="payment">
		<div class="row">
			<div class="col-sm-2">
				<xsl:value-of select="cbc:PaymentMeansCode"/>
			</div>
			<div class="col-sm-10">
				<dl class="row">
					<dt class="col-sm-4">PaymentID</dt>
					<dd class="col-sm-8">
						<xsl:value-of select="cbc:PaymentID"/>
					</dd>
					<dt class="col-sm-4">Account</dt>
					<dd class="col-sm-8">
						<xsl:value-of select="cac:PayeeFinancialAccount/cbc:ID"/> (<xsl:value-of select="cac:PayeeFinancialAccount/cac:FinancialInstitutionBranch/cbc:ID"/>)</dd>
				</dl>
			</div>
		</div>
	</xsl:template>
	<xsl:template match="cac:PaymentTerms" mode="payment">
		<xsl:apply-templates select="cbc:Note" mode="common"/>
	</xsl:template>
	<xsl:template match="cac:TaxTotal" mode="tax">
		<table>
			<thead>
				<tr>
					<th>
						<xsl:copy-of select="u:label('tax', 'Category')"/>
					</th>
					<th style="width: 20%;">
						<xsl:copy-of select="u:label('tax', 'TaxableAmount')"/>
					</th>
					<th style="width: 20%;">
						<xsl:copy-of select="u:label('tax', 'TaxAmount')"/>
					</th>
				</tr>
			</thead>
			<tbody>
				<xsl:apply-templates select="cac:TaxSubtotal" mode="tax"/>
			</tbody>
			<tfoot>
				<xsl:apply-templates select="cbc:TaxAmount" mode="tax"/>
				<xsl:apply-templates select="../cac:TaxTotal[not(cac:TaxSubtotal)]/cbc:TaxAmount" mode="tax"/>
			</tfoot>
		</table>
	</xsl:template>
	<xsl:template match="cac:TaxSubtotal" mode="tax">
		<tr>
			<td>
				<xsl:apply-templates select="cac:TaxCategory" mode="common"/>
			</td>
			<td class="text-right">
				<xsl:apply-templates select="cbc:TaxableAmount" mode="common"/>
			</td>
			<td class="text-right">
				<xsl:apply-templates select="cbc:TaxAmount" mode="common"/>
			</td>
		</tr>
	</xsl:template>
	<xsl:template match="cbc:TaxAmount" mode="tax">
		<tr>
			<td colspan="2">
				<xsl:copy-of select="u:label('tax', 'Total')"/>
			</td>
			<td class="text-right">
				<xsl:apply-templates select="." mode="common"/>
			</td>
		</tr>
	</xsl:template>
	<xsl:template match="cac:LegalMonetaryTotal" mode="total">
		<h3>
			<xsl:copy-of select="u:label('total', 'Totals')"/>
		</h3>
		<dl>
			<xsl:apply-templates select="cbc:TaxExclusiveAmount | cbc:TaxInclusiveAmount | cbc:PrepaidAmount | cbc:PayableRoundingAmount | cbc:PayableAmount" mode="total"/>
		</dl>
	</xsl:template>
	<xsl:template match="cbc:PayableAmount" mode="total">
		<dt>
			<xsl:copy-of select="u:label('total', local-name())"/>
		</dt>
		<dd>
			<strong>
				<xsl:apply-templates select="current()" mode="common"/>
			</strong>
		</dd>
	</xsl:template>
	<xsl:template match="cbc:*" mode="total">
		<dt>
			<xsl:copy-of select="u:label('total', local-name())"/>
		</dt>
		<dd>
			<xsl:apply-templates select="current()" mode="common"/>
		</dd>
	</xsl:template>
	<xsl:template match="*" mode="document">
		<html lang="{$language}">
			<head>
				<xsl:call-template name="doc-head"/>
				<title>Unknown document type</title>
			</head>
			<body>
				<div id="document">
					<h1>Unknown document type</h1>
					<p class="lead">The document you tried to render were not recognized.</p>
				</div>
			</body>
		</html>
	</xsl:template>
</xsl:stylesheet>
