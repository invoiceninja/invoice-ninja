<?xml version="1.0"?><?xar XSLT?>

<!-- 
   OVERVIEW
   
   ASCC/Schematron.com Skeleton Module for ISO Schematron (for XSLT2 systems)
   
   ISO Schematron is a language for making assertion about the presence or absense
   of patterns in XML documents. It is typically used for as a schema language, or
   to augment existing schema languages, and to check business rules. It is very
   powerful, yet quite simple: a developer only need know XPath and about five other
   elements.
   
   This is an open source implementation of ISO Schematron in XSLT. Although ISO does
   not allow reference implementations which might compete with the text of the
   standard, this code has been compiled by Rick Jelliffe, inventor of Schematron
   and editor of the ISO standard; so developers can certainly use it as an 
   unofficial reference implementation for clarification. 
   
   This implementation is based on one by Oliver Becker. API documentation is 
   available separately; try www.schematron.com for this. Funding for this
   stylesheet over the years has come from Topologi Pty. Ltd., Geotempo Ltd.,
   and ASCC, Tapei.
   
   There are two versions of this skeleton: one is tailored for XSLT1 processors
   and the other is tailored for XSLT2 processors. Future versions of the
   XSLT2 skeleton may support more features than that the XSLT 1 skeleton.
-->
  
<!--
Open Source Initiative OSI - The MIT License:Licensing
[OSI Approved License]

This source code was previously available under the zlib/libpng license. 
Attribution is polite.

The MIT License

Copyright (c)  2000-2010 Rick Jellife and Academia Sinica Computing Centre, Taiwan.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
-->
<!--
   TIPS
      
   A tip for new users of Schematron: make your assertions contain positive messages
   about what is expected, rather than error messages. For example, use the form
   "An X should have a Y, because Z". 
   
   Another tip is that Schematron provides an
   element <iso:ns> for declaring the namespaces and prefixes used in Xpaths in 
   attribute values; it does not extend the XML Namespaces mechanism: if a name
   in an XPath has a prefix, there must be an <iso:ns> element for that prefix; if
   a name in an XPath does not have a prefix, it is always in no namespace.
   
   A tip for implementers of Schematron, either using this API or re-implementing it:
   make the value of the diagnostics, flags and richer features available if possible;
   Schematron has many of the optional richer features which, if implemented, provide
   a compelling alternative approach to validation and business-rules checking compared
   to other schema languages and programs. 
   
   If you create your own meta-stylesheet to override this one, it is a
   good idea to have both in the same directory and to run the stylesheet
   from that directory, as many XSLT implementations have ideosyncratic
   handling of URLs: keep it simple.
-->
 

<!--
  INVOCATION INFORMATION
  
  The following parameters are available
  
    phase           NMTOKEN | "#ALL" (default) Select the phase for validation
    allow-foreign   "true" | "false" (default)   Pass non-Schematron elements to the generated stylesheet 
    sch.exslt.imports semi-colon delimited string of filenames for some EXSLT implementations  
    message-newline "true" (default) | "false"   Generate an extra newline at the end of messages 
    debug	    "true" | "false" (default)  Debug mode lets compilation continue despite problems
    attributes "true" | "false"  (Autodetecting) Use only when the schema has no attributes as the context nodes
    only-child-elements "true" | "false" (Autodetecting) Use only when the schema has no comments
    or PI  as the context nodes
    langCode		ISO language code      language for skeleton errors, if available
    terminate= yes | no | true | false | assert  Terminate on the first failed assertion or successful report
                                         Note whether any output at all is generated depends on the XSLT implementation.
                                         
  The following parameters can be specified as Schematron variables in diagnostics, assertions and so on.
    fileNameParameter string	  
    fileDirParameter string				
    archiveNameParameter string	  In case of ZIP files
    archiveDirParameter string	  In case of ZIP files	 
    
 Experimental: USE AT YOUR OWN RISK   
    visit-text "true" "false"   Also visist text nodes for context. WARNING: NON_STARDARD.
    select-contents '' | 'key' | '//'   Select different implementation strategies
 
 Conventions: Meta-stylesheets that override this may use the following parameters
    generate-paths=true|false   generate the @location attribute with XPaths
    full-path-notation = 1|2|3  select the notation for the full paths: 1=computer, 2=human, 3=obsolescent
    diagnose= yes | no    Add the diagnostics to the assertion test in reports
 
-->

<!-- 
  XSLT VERSION SUPPORT

  XSLT 1:
     A schema using the standard XSLT 1 query binding will have a /schema/@queryBinding='xslt' or 
     nothing.

       * Note: XT does not implement key() and will die if given it. 
       * Add all formal parameters to default templates
       * Fix missing apply-templates from process-ns and add params back

  EXSLT:  Experimental support
     A schema using the EXSLT query binding will have a /schema/@queryBinding='exslt'.
     It is built on XSLT 1. After experience is gained, this binding is expected to be 
     formalized as part of ISO Schematron, which currently reserves the "exslt" name for this purpose.

     Some EXSLT engines have the extra functions built-in. For these, there is no need to
     provide library locations. For engines that require the functions, either hard code
     them in this script or provide them on the command-line argument.
      
  XSLT 2:   Experimental support
     A schema using the XSLT 2 query binding will have a /schema/@queryBinding='xslt2'.
     This binding is expected to be formalized as part of ISO
     Schematron, which currently reserves the "xslt2" name for this purpose.
     The xsl:import-schema, xsl:key and xsl:function elements are allowed as top elements. 
     
  XPATH:    Experimental support
     A schema using the XPATH query binding will have a /schema/@queryBinding='xpath'.
     It can run with XSLT 1 and is a strict superset of default ISO Schematron. After
     experience is gained, this binding is expected to be formalized as part of ISO
     Schematron, which currently reserves the "xpath" name for this purpose.

     The intent of this query binding is to support minimal non-XSLT implementations of 
     Schematron that use simple XPath APIs. These not only have fewer functions available
     than the XSLT version of XPath, but some of them do not support variables. 
     Consequently, in this binding, the <let> element and command-line variables passed
     to the schema should not be used? 
     The xsl:import-schema element is not allowed.
     
-->
<!--
   PROCESS INFORMATION
   
   This stylesheet compiles a Schematron schema (*.sch) into XSLT code (*.xsl). 
   The generated XSLT code can then be run against an XML file (*.xml, etc) and
   will produce validation results.
   
   The output of validation results is performed using named templates (process-*). 
   These can be overridden easily by making a new XSLT stylesheet that imports this 
   stylesheet but has its own version of the relevant process-* templates. Several
   of these invoking stylesheets are available: "iso_svrl.xsl", for example generates
   ISO Schematron Validation Report Language format results.
   
   In this version of the stylesheet, the ISO feature called "abstract patterns" is
   implemented using macro processing: a prior XSLT stage to which converts uses
   of abstract patterns into normal patterns. If you do not use abstract patterns,
   it is not necessary to preprocess the schema.
   
   To summarize, a basic process flow for some commandline processor is like this:
     XSLT -input=xxx.sch  -output=xxx.xsl  -stylesheet=iso_schematron_skeleton.xsl
     XSLT -input=document.xml  -output=xxx-document.results  -stylesheet=xxx.xsl
   
   iso_svrl.xslt is an implementation of Schematron that can use this skeleton and
   generate ISO SVRL reports. A process flow for some commandline processor would
   be like this:
     XSLT -input=xxx.sch  -output=xxx.xsl  -stylesheet=iso_svrl.xsl
     XSLT -input=document.xml  -output=xxx-document.results  -stylesheet=xxx.xsl
     
   It is not impossible that ultimately a third stage, to handle macro-preprocessing
   and inclusion, might be necessary. (The trade-off is in making this XSLT more
   complex compared to making the outer process more complex.)
             
  This version has been developed to work with 
     Saxon 9
  For versions for XSLT 1 processors, see www.xml.com

 Please note that if you are using SAXON and JAXP, then you should use 
  System.setProperty("javax.xml.transform.TransformerFactory",
                          "net.sf.saxon.TransformerFactoryImpl");
 rather than 
  System.setProperty("javax.xml.xpath.TransformerFactory",
                           "net.sf.saxon.TransformerFactoryImpl");
 which is does not work, at least for the versions of SAXON we tried.
--> 
<!--
  VERSION INFORMATION
     2010-04-14
     	* RJ Reorder call-template in exslt case only, report by BD
        * Add command line parameter 'terminate' which will terminate on first failed 
        assert and (optionally) successful report. 
     2010-01-24
     	* RJ Allow let elements to have direct content instead of @value 
     2009-08020 
        * RJ Give better scoping to resolution of abstract rules
     2009-07-07
     	* RJ Fix up warning on looking for @* on root  TODO CHECK!!!!
     2009-05-10 
     	* RJ Fix up incorrect use of tunnel
     2009-02-25 
        * RJ Fix up variable names so none are used twice in same template
      2009-02-19
        * RJ add experimental support for pattern/@documents 
        This takes an XPath that returns a sequence of strings, treats them as 
        relative URI references, and goes through these. It may need to be expanded
        to allow absolute paths.
    2008-09-19 RJ
        * Add mode schematron-select-full-path and param full-path-notation 
        
   2008-08-19
   		* RJ Add experimental property element.
   		This acts like the diagnostics element. An attribute rule/@properties,
   		assert/@properties and report/@properties can contain a list of ids
   		which reference a /schema/proporties/property/@id. This is a property
   		which will be carried over to the output, eg SVRL. It can have @name,
   		and the value is @value or the contents. Properties on rules are 
   		properties regardless of validation: they should apply to the subject
   		element. Properties on asserts and reports only make it through in the
   		negative case, at the moment, so they are really just a place to hold
   		structured info, eg. for diagnostics. The properties mechanism addresses
   		a bother with Schematron, that you have to post-process the document to
    	get any kind of structured data that might be nice in the output report. 
   		 
   		
   2008-08-14
   		* RJ move all messages into localization strings, add langCode parameter, 
   		named template to call external file in same directory as this xslt.
   		The file should be called sch-message-$langCode.xhtml ($langCode example "en")
  
   2008-08-11
   		* TT report/@flag was missing
   2008-08-06
   		* TT Top-level lets need to be implemented using xsl:param not xsl:variable
   		* TT xsl:param/@select must have XPath or not be specified
   		
   2008-08-04 
   		* RJ add saxon namespace to output to allow extension functions
   Version: 2008-07-28
   		* KH schematron-get-full-path-3 has [index] even on top step
   Version: 2008-07-24
   		* RJ clean out commented out namespace handling code 
   		* RJ allow schema/@queryBinding='xpath2' and warn if variables are
   		used
   		
   Version: 2008-07-14 update for XSLT2 and inclusion experiments
   		* RJ Clean up zero-length fragment test on include
   		* RJ Add experimental support for include containers
   		* RJ Add support for xsl:import-schema (request Paul Hermans)
   		* RJ Add support for xsl:function
   		* RJ For path generation, test for //iso:schema not just /iso:schema, for potential embedded Schematron support
   		* RJ Don't generate double error messages for old namespace elements
   		* RJ Experimental iso:rule/iso:title just kept as comment (bigger request Uche Ogbuji)
   		* RJ Fix bug that prevented including patterns in this (report Roger
   	Costello)
   Version: 2007-10-17
     Forked out version just to support SAXON 8 and potentially other XSLT2 processors.
       * RJ use xsl:namespace element
       * RJ use schold as namespace for old schematron, to prevent SAXON complaining
         when validating the Schematron schema for Schematron
       * RJ fix FULL-PATH for attributes

   Version: 2007-07-19
     Accept most changes in David Carlisle's fork, but continue as XSLT1 script: 
    	http://dpcarlisle.blogspot.com/search/label/schematron
    	* DPC Remove "optimize" parameter
    	* DPC Add autodetecting optimize parameter attribute to skip checking attribute
    	context
    	* DPC Add autodetecting optimize parameter only-child-elements turn off checking for 
    	comments and PIs
    	* DPC (Experimental: NON_STANDARD DANGER!) Add param visit-text to viist text
    	nodes too for context 
    	* DPC Fix inclusion syntax to allow #
    	* DPC Priorities count up from 1000 not down from 4000 to allow more rules
        * RJ Add new template for titles of schemas, with existing behaviour.  
        Override process-schema-title for custom processing of title
    		
    
   Version: 2007-04-04
   	* RJ debug mode param
	* RJ alter mixed test to only test mixed branches, so the same document
	could have old and new namespaces schemas in it, but each schema must
	be distinct, just so as not to overconstrain things.
   	* KH zero-length include/@href is fatal error, but allow debug mode
	* SB add hint on SAXON and JAXP
	* DC generate-full-path-1 generates XLST1 code by default
   Version: 2007-03-05
      	* AS Typo for EXSLT randome, improve comment
      	* KH get-schematron-full-path-2 needs to apply to attributes too
      	* DP document policy on extensions better
      	* DC use copy-of not copy for foreign elements
      	* DC add generate-path-2
      	* DC don't try to apply templates to attribute axis on attribute nodes, to
      	stop SAXON warning.
      	* RJ improve reporting of typos 
   
   Version: 2007-02-08
   		* KH Schematron fullpath implementation: @* handled twice and / missing
   		* KH Change stylesheetbody from named template to mode to allow implementers more flexibility.
   		  Move process-ns to outside the stylesheet body.
   		* DP, FG, fix handling of xslt:key
   		* FG no iso:title/@class
   		* Experimental optimization 'visit-no-attributes'
   		* KH Experimental added schematron-get-full-path-2 which gives prefixed version for humans
 		* DC Move stylesheet/@version generation to after namespace handling
 		* DC, FG EXSLT namespace handling code
 		* FG add ref and commented code from FG's page on namespaces
 		* Start adding normalize-space() to parameter code
 		* Add a space between diagnostics
   		   		 
   Version: 2007-01-22
   	* DP change = ($start) to = $start and =($phase) to =$phase 
   	to run under Saxon 8.8j
	* FG better title section using ( @id | iso:title)[last()]
	* Default query language binding is "xslt" not "xslt1"
  
   Version: 2007-01-19
   		* Simplify message newline code
   		* Remove termination and xpath appending to message options: 
   		   factor out as  iso_schematron_terminator.xsl
   		* Comment out XSLT2 namespace fix temporarily
  
   Version: 2007-01-18 (First beta candidate for comment)
          * DC remove xml:space="preserve"
          * FG improve comment on import statement
          * DC improve comments on invocation section
          * Add exploratory support for iso:schema[@queryBinding='xpath']
             by allowing it and warning as lets are found
          * Be strict about queryBinding spelling errors
          * Extra comments on the different queryBindings
          * KH Add option "message-paths" to generate XPath from output 
          * KH Add option "terminate" to halt with an error after the first assertion
          * KH refactor paths in schematron-full-path
          * Improve (?) namespace handling: no dummy attributes for prefix "xsl" generated
   
   Version: 2007-01-15
          * FG fix for calling templates
          * Add formal parameters to default templates: may help XSLT 2
          * Fix get-schematron-full-path
          * Include skeleton1-6 is commented out by default

   Version:2007-01-12 (Pre-beta release to Schematron-love-in maillist)
           * Add many extra parameters to the process-* calls, so that almost
           all the information in the schema can be provided to client programs.
           Also, rearrange the parameters to fit in with the ISO schema, which
           has "rich" and "linkable" attribute groups.
           * Warn on diagnostics with no ID once only
           * Improved path reporting, to handle for namespaces
           * Add process-title dummy template for API
           * Add command-line parameter allow-foreign (true|false) to suppress
            warnings one foreign elements and pass them through to the generated
            stylesheet
           * remove legacy templates for the old ASCC namespace and no namespace, 
              and use an import statement instead. Much cleaner now!
           * patterns use @id not @name
           * titles can contain sub-elements
           * start change iso:rule to allow attributes, PIs and comments 
           * the default process-* for inline elements add a leading and trailing 
             space, to reduce the chance of concatenation.
           * add comments to make the generated code clearer
           
   Version:2006-11-07 (ISO: first release private to schematron-love-in maillist for review)
           * Duplicate pattern templates, for handling ISO namespace
           * Add priority onto default and paragraph templates
           * Add namespace checks
           * Handle key in xsl namespace not iso
           * Add include
           * Improve namespace handling
           * Preliminary XSLT2 and EXSLT support
	       * Refactor iso:schema for clarity

    Version: 2003-05-26 
    	    * Fix bug with key 
    Version: 2003-04-16
    	   * handle 1.6 let expressions
    	   * make key use XSLT names, and allow anywhere
    Version: 2001-06-13
           * same skeleton now supports namespace or no namespace
           * parameters to handlers updated for all 1.5 attributes 
           * diagnostic hints supported: command-line option diagnose=yes|no
           * phases supported: command-line option phase=#ALL|...
           * abstract rules
           * compile-time error messages  
	   * add utility routine generate-id-from-path
          
    Contributors: Rick Jelliffe (original), Oliver Becker (architecture, XSLT2), 
             Miloslav Nic (diagnostic, phase, options), Ludwig Svenonius (abstract)
             Uche Ogbuji (misc. bug fixes), Jim Ancona (SAXON workaround),
	 	     Francis Norton (generate-id-from-path), Robert Leftwich, Bryan Rasmussen,
             Dave Pawson (include, fallback), Florent Georges (namespaces, exslt, attribute
             context), Benoit Maisonny (attribute context), John Dumps (process-message newline),
             Cliff Stanford (diagnostics and other newlines)

    
      
    
    KNOWN TYPICAL LIMITATIONS:
      * Don't use <iso:ns prefix="xsl" .../> with a namespace other than the standard
      XSLT one. This would be a bizarre thing to do anyway. 
      * Don't use other prefixes for the XSLT namespace either; some implementations will
      not handle it correctly.
     
     EXTENSIONS:
      ISO Schematron is designed as a framework with some standard query language
      bindings. If you need to support other features, please do so safely by making
      up your own @queryLanguage name: this makes it clear that your schema requires
      special features. For example, default ISO Schematron does not support user
      defined functions; so if you want to use the user defined function feature
      in XSLT, you need to have a schema with some queryBinding attribute name like
      "XSLT-with-my-functions" or whatever.
-->




<xsl:stylesheet  
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform" 
	xmlns:axsl="http://www.w3.org/1999/XSL/TransformAlias" 
    xmlns:schold="http://www.ascc.net/xml/schematron"
    xmlns:iso="http://purl.oclc.org/dsdl/schematron" 
    xmlns:exsl="http://exslt.org/common" 
    xmlns:xhtml="http://www.w3.org/1999/xhtml" 
    xmlns:xs="http://www.w3.org/2001/XMLSchema"
    extension-element-prefixes="exsl"
    version="2.0"
	 >
<!-- This program implements ISO Schematron, except for abstract patterns 
which require a preprocess.
-->
  

<xsl:namespace-alias stylesheet-prefix="axsl" result-prefix="xsl"/>


<!-- Category: top-level-element -->
<xsl:output method="xml" omit-xml-declaration="no" standalone="yes"  indent="yes"/>



<xsl:param name="phase">
   <xsl:choose>   
    <xsl:when test="//iso:schema/@defaultPhase">
      <xsl:value-of select="//iso:schema/@defaultPhase"/>
    </xsl:when>
    <xsl:otherwise>#ALL</xsl:otherwise>
  </xsl:choose>
</xsl:param>

<xsl:param name="allow-foreign">false</xsl:param>

<xsl:param name="message-newline">true</xsl:param>

<!-- DPC set to true if contexts should be checked on attribute nodes
         defaults to true if there is any possibility that a context could match an attribute,
         err on the side if caution, a context of *[.='@'] would cause this param to defualt to true
         even though @ is in a string
-->
<xsl:param name="attributes">
  <xsl:choose>
    <xsl:when test="//iso:rule[contains(@context,'@') or contains(@context,'attribute')]">true</xsl:when>
    <xsl:otherwise>false</xsl:otherwise>
  </xsl:choose>
</xsl:param>

<!-- DPC set to true if contexts should be checked on just elements in the child axis
         defaults to true if there is any possibility that a context could match an comment or PI
         err on the side if caution, a context of *[.='('] would cause this param to defualt to true
         even though ( is in a string, but node() comment() and processing-instruction()  all have a (
-->
<xsl:param name="only-child-elements">
  <xsl:choose>
    <xsl:when test="//iso:rule[contains(@context,'(')]">true</xsl:when>
    <xsl:otherwise>false</xsl:otherwise>
  </xsl:choose>
</xsl:param>

<!-- DPC set to true if contexts should be checked on text nodes nodes (if only-child-elements is false)
         THIS IS NON CONFORMANT BEHAVIOUR JUST FOR DISCUSSION OF A POSSIBLE CHANGE TO THE
         SPECIFICATION. THIS PARAM SHOULD GO IF THE FINAL DECISION IS THAT THE SPEC DOES NOT CHANGE.
	 Always defaults to false
-->
<xsl:param name="visit-text" select="'false'"/>

<!-- DPC
  When selecting contexts the specified behaviour is
    @*|node()[not(self::text())]
    The automatic settings may use
      node()[not(self::text())]
      @*|*
      *
  instead for schema for which they are equivalent.
  If the params are set explictly the above may be used, and also either if
      @*
      @*|node()
   in all cases the result may not be equivalent, for example if you specify no attributes and the schema 
   does have attribute contexts they will be silently ignored.

  after testing it turns out that
  node()[not(self::text())] is slower in saxon than *|comment()|processing-instruction() 
  which I find a bit surprising but anyway I'll use the longr faster version.
-->
<xsl:variable name="context-xpath">
  <xsl:if test="$attributes='true' and parent::node() ">@*|</xsl:if>
  <xsl:choose>
    <xsl:when test="$only-child-elements='true'">*</xsl:when>
    <xsl:when test="$visit-text='true'">node()</xsl:when>
    <xsl:otherwise>*|comment()|processing-instruction()</xsl:otherwise>
  </xsl:choose>
</xsl:variable>

<!-- DPC if this is set to 
    '' use recursive templates to iterate over document tree,
    'key' select  all contexts with a key rather than walking the tree explictly in each mode
    '//' select all contexts with // a key rather than walking the tree explictly in each mode (XSLT2 only)
-->
<xsl:param name="select-contexts" select="''"/>


<!-- e.g. saxon file.xml file.xsl "sch.exslt.imports=.../string.xsl;.../math.xsl" -->
<xsl:param name="sch.exslt.imports"/>

<xsl:param name="debug">false</xsl:param>

<!-- Set the language code for messages -->
<xsl:param name="langCode">default</xsl:param>

<!-- Set the default for schematron-select-full-path, i.e. the notation for svrl's @location-->
<xsl:param name="full-path-notation">1</xsl:param>

<xsl:param name="terminate">false</xsl:param>

<!-- Simple namespace check -->
<xsl:template match="/">
    <xsl:if  test="//schold:*[ancestor::iso:* or descendant::iso:*]">
    
	<xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">1</xsl:with-param></xsl:call-template></xsl:message>
 
    </xsl:if>

    <xsl:apply-templates />
</xsl:template>


<!-- ============================================================== -->
<!-- ISO SCHEMATRON SCHEMA ELEMENT  -->
<!-- Not handled: Abstract patterns. A pre-processor is assumed. -->
<!-- ============================================================== -->

<!-- SCHEMA -->
<!-- Default uses XSLT 1 -->
<xsl:template match="iso:schema[not(@queryBinding) or @queryBinding='xslt' 
     or @queryBinding='xslt1' or @queryBinding='XSLT' or @queryBinding='XSLT1'
     or @queryBinding='xpath']">
     <xsl:if test="
	     @queryBinding='xslt1' or @queryBinding='XSLT' or @queryBinding='XSLT1'">
	     <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">2</xsl:with-param></xsl:call-template></xsl:message>
	</xsl:if>
	<axsl:stylesheet>
	    <xsl:apply-templates 
		select="iso:ns" />

	    <!-- Handle the namespaces before the version attribute: reported to help SAXON -->
	    <xsl:attribute name="version">1.0</xsl:attribute>
	    
		<xsl:apply-templates select="." mode="stylesheetbody"/>
		<!-- was xsl:call-template name="stylesheetbody"/ -->
	</axsl:stylesheet>
</xsl:template>

<!-- Using EXSLT with all modeles (except function module: not applicable) -->
<xsl:template match="iso:schema[@queryBinding='exslt']" priority="10">
    <xsl:comment>This XSLT was automatically generated from a Schematron schema.</xsl:comment>
	<axsl:stylesheet
 	  	xmlns:date="http://exslt.org/dates-and-times"
 	  	xmlns:dyn="http://exslt.org/dynamic"
		xmlns:exsl="http://exslt.org/common"
		xmlns:math="http://exslt.org/math"
   		xmlns:random="http://exslt.org/random"
  		xmlns:regexp="http://exslt.org/regular-expressions"
   		xmlns:set="http://exslt.org/sets"
   		xmlns:str="http://exslt.org/strings"
   		extension-element-prefixes="date dyn exsl math random regexp set str" >
	
        <xsl:apply-templates
		select="iso:ns" />
	    <!-- Handle the namespaces before the version attribute: reported to help SAXON -->
	    <xsl:attribute name="version">1.0</xsl:attribute>
	    
	    <xsl:apply-templates select="." mode="stylesheetbody"/>
		<!-- was xsl:call-template name="stylesheetbody"/ -->
	</axsl:stylesheet>
</xsl:template>

<!-- Using XSLT 2 -->
<xsl:template 
	match="iso:schema[@queryBinding='xslt2' or @queryBinding ='xpath2']" 
	priority="10">
	<axsl:stylesheet
	   xmlns:xs="http://www.w3.org/2001/XMLSchema" 
	   xmlns:xsd="http://www.w3.org/2001/XMLSchema" 
	   xmlns:saxon="http://saxon.sf.net/" 
	   >
        <xsl:apply-templates 
		select="iso:ns" />
	    <!-- Handle the namespaces before the version attribute: reported to help SAXON -->
	    <xsl:attribute name="version">2.0</xsl:attribute>
	    
		<xsl:apply-templates select="." mode="stylesheetbody"/>
		<!-- was xsl:call-template name="stylesheetbody"/ -->
	</axsl:stylesheet>
</xsl:template>


<!-- Uses unknown query language binding -->
<xsl:template match="iso:schema" priority="-1">
	<xsl:message terminate="yes" ><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">3a</xsl:with-param></xsl:call-template>
	<xsl:value-of select="@queryBinding"/>
	<xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">3b</xsl:with-param></xsl:call-template></xsl:message>        
</xsl:template>

<xsl:template match="*" mode="stylesheetbody">
	<!--xsl:template name="stylesheetbody"-->
    <xsl:comment>Implementers: please note that overriding process-prolog or process-root is 
    the preferred method for meta-stylesheets to use where possible. </xsl:comment><xsl:text>&#10;</xsl:text>
 
    <!-- These parameters may contain strings with the name and directory of the file being
   validated. For convenience, if the caller only has the information in a single string,
   that string could be put in fileDirParameter. The archives parameters are available
   for ZIP archives.
	-->
    
    <xsl:call-template name="iso:exslt.add.imports" /> <!-- RJ moved report BH -->
	<axsl:param name="archiveDirParameter" />
	<axsl:param name="archiveNameParameter" />
	<axsl:param name="fileNameParameter"  />
	<axsl:param name="fileDirParameter" /> 
	
       
    <axsl:variable name="document-uri"><axsl:value-of select="document-uri(/)" /></axsl:variable>
    <xsl:text>&#10;&#10;</xsl:text><xsl:comment>PHASES</xsl:comment><xsl:text>&#10;</xsl:text>
	<xsl:call-template name="handle-phase"/> 
    <xsl:text>&#10;&#10;</xsl:text><xsl:comment>PROLOG</xsl:comment><xsl:text>&#10;</xsl:text>
	<xsl:call-template name="process-prolog"/> 
    <xsl:text>&#10;&#10;</xsl:text><xsl:comment>XSD TYPES FOR XSLT2</xsl:comment><xsl:text>&#10;</xsl:text>
	<xsl:apply-templates mode="do-types"   select="xsl:import-schema"/>
    <xsl:text>&#10;&#10;</xsl:text><xsl:comment>KEYS AND FUNCTIONS</xsl:comment><xsl:text>&#10;</xsl:text>
	<xsl:apply-templates mode="do-keys"   select="xsl:key | xsl:function "/>
    <xsl:text>&#10;&#10;</xsl:text><xsl:comment>DEFAULT RULES</xsl:comment><xsl:text>&#10;</xsl:text>
    <xsl:call-template name="generate-default-rules" />
    <xsl:text>&#10;&#10;</xsl:text><xsl:comment>SCHEMA SETUP</xsl:comment><xsl:text>&#10;</xsl:text>
    <xsl:call-template name="handle-root"/>
    <xsl:text>&#10;&#10;</xsl:text><xsl:comment>SCHEMATRON PATTERNS</xsl:comment><xsl:text>&#10;</xsl:text>
 
	<xsl:apply-templates select="*[not(self::iso:ns)] " />
</xsl:template>
 
    <xsl:template name="iso:exslt.add.imports">
      <xsl:param name="imports" select="$sch.exslt.imports"/>
      <xsl:choose>
        <xsl:when test="contains($imports, ';')">
          <axsl:import href="{ substring-before($imports, ';') }"/>
          <xsl:call-template name="iso:exslt.add.imports">
            <xsl:with-param name="imports"  select="substring-after($imports, ';')"/>
          </xsl:call-template>
        </xsl:when>
        <xsl:when test="$imports">
          <axsl:import href="{ $imports }"/>
        </xsl:when>
      </xsl:choose>
    </xsl:template>

<xsl:template name="handle-phase" >
    <!-- This just tests that the phase exists -->
	<xsl:if test="not(normalize-space( $phase ) = '#ALL')">
	  <xsl:if test="not(iso:phase[@id = normalize-space( $phase )])">
		  <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">4a</xsl:with-param></xsl:call-template>
		  <xsl:value-of select="normalize-space( $phase )"/>
		  <xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">4b</xsl:with-param></xsl:call-template></xsl:message>
	  </xsl:if>
     </xsl:if>
</xsl:template>

<xsl:template name="generate-default-rules">
		<xsl:text>&#10;&#10;</xsl:text>
		<xsl:comment>MODE: SCHEMATRON-SELECT-FULL-PATH</xsl:comment><xsl:text>&#10;</xsl:text>
		<xsl:comment>This mode can be used to generate an ugly though full XPath for locators</xsl:comment><xsl:text>&#10;</xsl:text>
   		<axsl:template match="*" mode="schematron-select-full-path">
   			<xsl:choose>
   				<xsl:when test=" $full-path-notation = '1' ">
   					<!-- Use for computers, but rather unreadable for humans -->
					<axsl:apply-templates select="." mode="schematron-get-full-path"/>
				</xsl:when>
   				<xsl:when test=" $full-path-notation = '2' ">
   					<!-- Use for humans, but no good for paths unless namespaces are known out-of-band -->
					<axsl:apply-templates select="." mode="schematron-get-full-path-2"/>
				</xsl:when>
   				<xsl:when test=" $full-path-notation = '3' "> 
   					<!-- Obsolescent. Use for humans, but no good for paths unless namespaces are known out-of-band -->
					<axsl:apply-templates select="." mode="schematron-get-full-path-3"/>
				</xsl:when>

                   <xsl:otherwise >
                       <!-- Use for computers, but rather unreadable for humans -->
                    <axsl:apply-templates select="." mode="schematron-get-full-path"/>
                </xsl:otherwise>
			</xsl:choose>
		</axsl:template>
	

		<xsl:text>&#10;&#10;</xsl:text>
		<xsl:comment>MODE: SCHEMATRON-FULL-PATH</xsl:comment><xsl:text>&#10;</xsl:text>
		<xsl:comment>This mode can be used to generate an ugly though full XPath for locators</xsl:comment><xsl:text>&#10;</xsl:text>
   		<axsl:template match="*" mode="schematron-get-full-path">
			<axsl:apply-templates select="parent::*" mode="schematron-get-full-path"/>
			<xsl:choose>
				<xsl:when test="//iso:schema[@queryBinding='xslt2']">
					<!-- XSLT2 syntax -->
			<axsl:text>/</axsl:text>		
			<axsl:choose>
      			<axsl:when test="namespace-uri()=''"><axsl:value-of select="name()"/></axsl:when>
      			<axsl:otherwise>
      				<axsl:text>*:</axsl:text>
      				<axsl:value-of select="local-name()"/>
      				<axsl:text>[namespace-uri()='</axsl:text>
      				<axsl:value-of select="namespace-uri()"/>
      				<axsl:text>']</axsl:text>
      			</axsl:otherwise>
    		</axsl:choose>
    		<axsl:variable name="preceding" select=
    		"count(preceding-sibling::*[local-name()=local-name(current())
	  		                             and namespace-uri() = namespace-uri(current())])" />
			<axsl:text>[</axsl:text>
	  		<axsl:value-of select="1+ $preceding"/>
	  		<axsl:text>]</axsl:text>
		</xsl:when>

		<xsl:otherwise>
			<!-- XSLT1 syntax -->

			<axsl:text>/</axsl:text>
			<axsl:choose>
			<axsl:when test="namespace-uri()=''">
			<axsl:value-of select="name()"/>
			<axsl:variable name="p_1" select="1+
			count(preceding-sibling::*[name()=name(current())])" />
		<axsl:if test="$p_1&gt;1 or following-sibling::*[name()=name(current())]">
		  <xsl:text/>[<axsl:value-of select="$p_1"/>]<xsl:text/>
		</axsl:if>
		</axsl:when>
		<axsl:otherwise>
		<axsl:text>*[local-name()='</axsl:text>
		<axsl:value-of select="local-name()"/>
		<axsl:text>']</axsl:text>
		<axsl:variable name="p_2" select="1+
		count(preceding-sibling::*[local-name()=local-name(current())])" />
		<axsl:if test="$p_2&gt;1 or following-sibling::*[local-name()=local-name(current())]">
		  <xsl:text/>[<axsl:value-of select="$p_2"/>]<xsl:text/>
		</axsl:if>
		</axsl:otherwise>
		</axsl:choose> 
		</xsl:otherwise>

	</xsl:choose>
       	 	</axsl:template>
       	 	
       	 	
		<axsl:template match="@*" mode="schematron-get-full-path">
			<xsl:choose>
				<xsl:when test="//iso:schema[@queryBinding='xslt2']">
					<!-- XSLT2 syntax -->
			<axsl:apply-templates select="parent::*" mode="schematron-get-full-path"/>
      		<axsl:text>/</axsl:text>
			<axsl:choose>
      			<axsl:when test="namespace-uri()=''">@<axsl:value-of select="name()"/></axsl:when>
      			<axsl:otherwise>
      				<axsl:text>@*[local-name()='</axsl:text>
      				<axsl:value-of select="local-name()"/>
      				<axsl:text>' and namespace-uri()='</axsl:text>
      				<axsl:value-of select="namespace-uri()"/>
      				<axsl:text>']</axsl:text>
      			</axsl:otherwise>
    		</axsl:choose>
	</xsl:when>

		<xsl:otherwise>
			<!-- XSLT1 syntax -->
		<axsl:text>/</axsl:text>
		<axsl:choose>
		<axsl:when test="namespace-uri()=''">@<axsl:value-of
		select="name()"/></axsl:when>
		<axsl:otherwise>
		<axsl:text>@*[local-name()='</axsl:text>
		<axsl:value-of select="local-name()"/>
		<axsl:text>' and namespace-uri()='</axsl:text>
		<axsl:value-of select="namespace-uri()"/>
		<axsl:text>']</axsl:text>
		</axsl:otherwise>
		</axsl:choose> 

			</xsl:otherwise>
			</xsl:choose>
		</axsl:template>
	
	<xsl:text>&#10;&#10;</xsl:text>
	
	<xsl:comment>MODE: SCHEMATRON-FULL-PATH-2</xsl:comment>
	<xsl:text>&#10;</xsl:text>
	<xsl:comment>This mode can be used to generate prefixed XPath for humans</xsl:comment>
	<xsl:text>&#10;</xsl:text>
	<!--simplify the error messages by using the namespace prefixes of the
     instance rather than the generic namespace-uri-styled qualification-->
	<axsl:template match="node() | @*" mode="schematron-get-full-path-2">
	<!--report the element hierarchy-->
		<axsl:for-each select="ancestor-or-self::*">
			<axsl:text>/</axsl:text>
			<axsl:value-of select="name(.)"/>
			<axsl:if test="preceding-sibling::*[name(.)=name(current())]">
				<axsl:text>[</axsl:text>
				<axsl:value-of
					select="count(preceding-sibling::*[name(.)=name(current())])+1"/>
				<axsl:text>]</axsl:text>
			</axsl:if>
		</axsl:for-each>
		<!--report the attribute-->
		<axsl:if test="not(self::*)">
			<axsl:text/>/@<axsl:value-of select="name(.)"/>
		</axsl:if>
	</axsl:template>


	<xsl:comment>MODE: SCHEMATRON-FULL-PATH-3</xsl:comment>
	
	<xsl:text>&#10;</xsl:text>
	<xsl:comment>This mode can be used to generate prefixed XPath for humans 
	(Top-level element has index)</xsl:comment>
	<xsl:text>&#10;</xsl:text>
	<!--simplify the error messages by using the namespace prefixes of the
     instance rather than the generic namespace-uri-styled qualification-->
	<axsl:template match="node() | @*" mode="schematron-get-full-path-3">
	<!--report the element hierarchy-->
		<axsl:for-each select="ancestor-or-self::*">
			<axsl:text>/</axsl:text>
			<axsl:value-of select="name(.)"/>
			<axsl:if test="parent::*">
				<axsl:text>[</axsl:text>
				<axsl:value-of
					select="count(preceding-sibling::*[name(.)=name(current())])+1"/>
				<axsl:text>]</axsl:text>
			</axsl:if>
		</axsl:for-each>
		<!--report the attribute-->
		<axsl:if test="not(self::*)">
			<axsl:text/>/@<axsl:value-of select="name(.)"/>
		</axsl:if>
	</axsl:template>

		<xsl:text>&#10;&#10;</xsl:text>
		<xsl:comment>MODE: GENERATE-ID-FROM-PATH </xsl:comment><xsl:text>&#10;</xsl:text>
		<!-- repeatable-id maker derived from Francis Norton's. -->
		<!-- use this if you need generate ids in separate passes,
		     because generate-id() is not guaranteed to produce the same
		     results each time. These ids are not XML names but closer to paths. -->
		<axsl:template match="/" mode="generate-id-from-path"/>
		<axsl:template match="text()" mode="generate-id-from-path">
			<axsl:apply-templates select="parent::*" mode="generate-id-from-path"/>
			<axsl:value-of select="concat('.text-', 1+count(preceding-sibling::text()), '-')"/>
		</axsl:template>
		<axsl:template match="comment()" mode="generate-id-from-path">
			<axsl:apply-templates select="parent::*" mode="generate-id-from-path"/>
			<axsl:value-of select="concat('.comment-', 1+count(preceding-sibling::comment()), '-')"/>
		</axsl:template>
		<axsl:template match="processing-instruction()" mode="generate-id-from-path">
			<axsl:apply-templates select="parent::*" mode="generate-id-from-path"/>
			<axsl:value-of 
			select="concat('.processing-instruction-', 1+count(preceding-sibling::processing-instruction()), '-')"/>
		</axsl:template>
		<axsl:template match="@*" mode="generate-id-from-path">
			<axsl:apply-templates select="parent::*" mode="generate-id-from-path"/>
			<axsl:value-of select="concat('.@', name())"/>
		</axsl:template>
		<axsl:template match="*" mode="generate-id-from-path" priority="-0.5">
			<axsl:apply-templates select="parent::*" mode="generate-id-from-path"/>
			<axsl:text>.</axsl:text>
<!--
			<axsl:choose>
				<axsl:when test="count(. | ../namespace::*) = count(../namespace::*)">
					<axsl:value-of select="concat('.namespace::-',1+count(namespace::*),'-')"/>
				</axsl:when>
				<axsl:otherwise>
-->
				<axsl:value-of 
				select="concat('.',name(),'-',1+count(preceding-sibling::*[name()=name(current())]),'-')"/>
<!--
				</axsl:otherwise>
			</axsl:choose>
-->
		</axsl:template>
		
		
		<xsl:text>&#10;&#10;</xsl:text>
		<xsl:comment>MODE: GENERATE-ID-2 </xsl:comment><xsl:text>&#10;</xsl:text>
		<!-- repeatable-id maker from David Carlisle. -->
		<!-- use this if you need generate IDs in separate passes,
		     because generate-id() is not guaranteed to produce the same
		     results each time. These IDs are well-formed XML NMTOKENS -->
	<axsl:template match="/" mode="generate-id-2">U</axsl:template>

	<axsl:template match="*" mode="generate-id-2" priority="2">
		<axsl:text>U</axsl:text>
		<axsl:number level="multiple" count="*"/>
	</axsl:template>

	<axsl:template match="node()" mode="generate-id-2">
		<axsl:text>U.</axsl:text>
		<axsl:number level="multiple" count="*"/>
		<axsl:text>n</axsl:text>
		<axsl:number count="node()"/>
	</axsl:template>

	<axsl:template match="@*" mode="generate-id-2">
		<axsl:text>U.</axsl:text>
		<axsl:number level="multiple" count="*"/>
		<axsl:text>_</axsl:text>
		<axsl:value-of select="string-length(local-name(.))"/>
		<axsl:text>_</axsl:text>
		<axsl:value-of select="translate(name(),':','.')"/>
	</axsl:template> 
		

		<xsl:comment>Strip characters</xsl:comment>
		<axsl:template match="text()" priority="-1" />
			
  </xsl:template>

 <xsl:template name="handle-root">
		<!-- Process the top-level element -->
		<axsl:template match="/">
			<xsl:call-template name="process-root">
				<xsl:with-param 	
				name="title" select="(@id | iso:title)[last()]"/>
				<xsl:with-param name="version" select="'iso'" />
				<xsl:with-param name="schemaVersion" select="@schemaVersion" />
				<xsl:with-param name="queryBinding" select="@queryBinding" />
				<xsl:with-param name="contents">
					<xsl:apply-templates mode="do-all-patterns"/>
				</xsl:with-param>
				
				<!-- "Rich" properties -->
				<xsl:with-param name="fpi" select="@fpi"/>
				<xsl:with-param name="icon" select="@icon"/>
				<xsl:with-param name="id" select="@id"/>
				<xsl:with-param name="lang" select="@xml:lang"/>
				<xsl:with-param name="see" select="@see" />
				<xsl:with-param name="space" select="@xml:space" />
			</xsl:call-template>
		</axsl:template>
 
      
</xsl:template>

<!-- ============================================================== -->
<!-- ISO SCHEMATRON ELEMENTS -->
<!-- ============================================================== -->

	<!-- ISO ACTIVE -->
	<xsl:template match="iso:active">
                <xsl:if test="not(@pattern)">
                    <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">5</xsl:with-param></xsl:call-template></xsl:message>
                </xsl:if>

                <xsl:if test="not(../../iso:pattern[@id = current()/@pattern])
                and not(../../iso:include)">
                           <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">6a</xsl:with-param></xsl:call-template>
                           <xsl:value-of select="@pattern"/>
					<xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">6b</xsl:with-param></xsl:call-template></xsl:message>
                </xsl:if>
        </xsl:template>

	<!-- ISO ASSERT and REPORT -->
	<xsl:template match="iso:assert">
  
                <xsl:if test="not(@test)">
                    <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">7</xsl:with-param></xsl:call-template></xsl:message>
                </xsl:if>
        <xsl:text>&#10;&#10;		</xsl:text>
		<xsl:comment>ASSERT <xsl:value-of select="@role" /> </xsl:comment><xsl:text>&#10;</xsl:text>      
	
		<axsl:choose>
			<axsl:when test="{@test}"/>
			<axsl:otherwise>
				<xsl:call-template name="process-assert">
					<xsl:with-param name="test" select="normalize-space(@test)" />
					<xsl:with-param name="diagnostics" select="@diagnostics"/>
					<xsl:with-param name="flag" select="@flag"/>
					
					<xsl:with-param name="properties" select="@properties" />
					
					<!-- "Rich" properties -->
					<xsl:with-param name="fpi" select="@fpi"/>
					<xsl:with-param name="icon" select="@icon"/>
					<xsl:with-param name="id" select="@id"/>
					<xsl:with-param name="lang" select="@xml:lang"/>
					<xsl:with-param name="see" select="@see" />
					<xsl:with-param name="space" select="@xml:space" />
					
					<!-- "Linking" properties -->
					<xsl:with-param name="role" select="@role" />
					<xsl:with-param name="subject" select="@subject" />
					 
				</xsl:call-template>
 			 
 			
			</axsl:otherwise>
		</axsl:choose>
	</xsl:template>
	<xsl:template match="iso:report">
		 
                <xsl:if test="not(@test)">
                    <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">8</xsl:with-param></xsl:call-template></xsl:message>
                </xsl:if>
                
        <xsl:text>&#10;&#10;		</xsl:text>
		<xsl:comment>REPORT <xsl:value-of select="@role" /> </xsl:comment><xsl:text>&#10;</xsl:text>      
	
		<axsl:if test="{@test}">
		
			<xsl:call-template name="process-report">
				<xsl:with-param name="test" select="normalize-space(@test)" />
				<xsl:with-param name="diagnostics" select="@diagnostics"/>
					<xsl:with-param name="flag" select="@flag"/>
				
					<xsl:with-param name="properties" select="@properties" />
					<!-- "Rich" properties -->
					<xsl:with-param name="fpi" select="@fpi"/>
					<xsl:with-param name="icon" select="@icon"/>
					<xsl:with-param name="id" select="@id"/>
					<xsl:with-param name="lang" select="@xml:lang"/>
					<xsl:with-param name="see" select="@see" />
					<xsl:with-param name="space" select="@xml:space" />
					
					<!-- "Linking" properties -->
					<xsl:with-param name="role" select="@role" />
					<xsl:with-param name="subject" select="@subject" />
			</xsl:call-template>
			 
		</axsl:if>
	</xsl:template>


	<!-- ISO DIAGNOSTIC -->
	<!-- We use a mode here to maintain backwards compatability, instead of adding it
	     to the other mode.
	-->
	<xsl:template match="iso:diagnostic" mode="check-diagnostics">
              <xsl:if test="not(@id)">
                    <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">9</xsl:with-param></xsl:call-template></xsl:message>
               </xsl:if>
    </xsl:template>
    
    <xsl:template match="iso:diagnostic"  >
                <xsl:call-template name="process-diagnostic">
                
					<!-- "Rich" properties -->
					<xsl:with-param name="fpi" select="@fpi"/>
					<xsl:with-param name="icon" select="@icon"/>
					<xsl:with-param name="id" select="@id"/>
					<xsl:with-param name="lang" select="@xml:lang"/>
					<xsl:with-param name="see" select="@see" />
					<xsl:with-param name="space" select="@xml:space" />
               </xsl:call-template>
                
        </xsl:template>

	<!-- ISO DIAGNOSTICS -->
	<xsl:template match="iso:diagnostics" >
		<xsl:apply-templates mode="check-diagnostics" select="*" />
	</xsl:template>

	<!-- ISO DIR -->
	<xsl:template match="iso:dir"  mode="text" >
		<xsl:call-template name="process-dir">
			<xsl:with-param name="value" select="@value"/>
		</xsl:call-template>
	</xsl:template>

	<!-- ISO EMPH -->
	<xsl:template match="iso:emph"  mode="text">
	 
		<xsl:call-template name="process-emph"/> 

	</xsl:template>

	<!-- ISO EXTENDS -->
	<xsl:template match="iso:extends">
		<xsl:if test="not(@rule)">
            <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">10</xsl:with-param></xsl:call-template></xsl:message>
        </xsl:if>
     	<xsl:if test="not(//iso:rule[@abstract='true'][@id= current()/@rule] )">
            <xsl:message>
                 <xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">11a</xsl:with-param></xsl:call-template>
                 <xsl:value-of select="@rule"/>
                 <xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">11b</xsl:with-param></xsl:call-template></xsl:message>
        </xsl:if>
	    <xsl:call-template name="IamEmpty" />

        <xsl:choose>
            <!-- prefer to use a locally declared rule -->
            <xsl:when test="parent::*/parent::*/iso:rule[@id=current()/@rule]">
    		    <xsl:apply-templates select="parent::*/parent::*/iso:rule[@id=current()/@rule]"
				    mode="extends"/>
            </xsl:when>
            <!-- otherwise use a global one: this is not in the 2006 standard -->
            <xsl:when test="//iso:rule[@id=current()/@rule]">
    		    <xsl:apply-templates select="//iso:rule[@id=current()/@rule]"
				    mode="extends"/>
            </xsl:when>
        </xsl:choose>
 

	</xsl:template>

	<!-- KEY: ISO has no KEY -->
	<!-- NOTE: 
	     Key has had a checkered history. Schematron 1.0 allowed it in certain places, but
	     users came up with a different location, which has now been adopted. 
	     
	     XT, the early XSLT processor, did not implement key and died when it was present. 
	     So there are some versions of the Schematron skeleton for XT that strip out all
	     key elements.
	     
	     Xalan (e.g. Xalan4C 1.0 and a Xalan4J) also had a funny. A fix involved making 
	     a top-level parameter called $hiddenKey and then using that instead of matching
	     "key". This has been removed.
	     
	     Keys and functions are the same mode, to allow their declaration to be mixed up.
	-->
	<xsl:template  match="xsl:key" mode="do-keys" >
	     <xsl:if test="not(@name)">
              <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">12</xsl:with-param></xsl:call-template></xsl:message>
         </xsl:if>
                <xsl:if test="not(@path) and not(@use)">
                    <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">13</xsl:with-param></xsl:call-template></xsl:message>
                </xsl:if>         
	     <xsl:choose>
	     	<xsl:when test="parent::iso:rule ">
	        <xsl:call-template name="IamEmpty" />
	       <xsl:choose>
	       	<xsl:when test="@path">
				<axsl:key match="{../@context}" name="{@name}" use="{@path}"/>
			</xsl:when>
			<xsl:otherwise>
							<axsl:key match="{../@context}" name="{@name}" use="{@use}"/>
			</xsl:otherwise>
			</xsl:choose>	
		</xsl:when>
		<xsl:otherwise>
                <xsl:if test="not(@match) ">
                    <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">14</xsl:with-param></xsl:call-template></xsl:message>
                </xsl:if>   		
			<axsl:key>
      			<xsl:copy-of select="@*"/>
    		</axsl:key>	
		</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

	<xsl:template match="xsl:key "  /><!-- swallow -->

	<xsl:template match="iso:key "  >
		<xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">15</xsl:with-param></xsl:call-template></xsl:message>
    </xsl:template>

  <!-- XSL FUNCTION -->
  <xsl:template  match="xsl:function" mode="do-keys" >
	     <xsl:if test="not(@name)">
              <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">16</xsl:with-param></xsl:call-template></xsl:message>
         </xsl:if>      
	     <xsl:copy-of select="."/>
  </xsl:template>

	<xsl:template match="xsl:function "  /><!-- swallow -->

	<xsl:template match="iso:function "  >
		<xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">17</xsl:with-param></xsl:call-template></xsl:message>
    </xsl:template>


   <!-- ISO INCLUDE -->
   <!-- This is only a fallback. Include really needs to have been done before this as a separate pass.-->

   <xsl:template match="iso:include[not(normalize-space(@href))]"
	   priority="1">
	<xsl:if test=" $debug = 'false' ">
		<xsl:message terminate="yes"><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">18</xsl:with-param></xsl:call-template></xsl:message>
	</xsl:if>

   </xsl:template>

   <!-- Extend the URI syntax to allow # refererences -->
   <!-- Note that XSLT2 actually already allows # references, but we override them because it
   looks unreliable -->
   <xsl:template match="iso:include">
       <xsl:variable name="document-uri" select="substring-before(concat(@href,'#'), '#')"/>
       <xsl:variable name="fragment-id" select="substring-after(@href, '#')"/>
       
       <xsl:choose> 
          
          <xsl:when test="string-length( $document-uri ) = 0 and string-length( $fragment-id ) = 0" >
          	<xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">19</xsl:with-param></xsl:call-template></xsl:message>
          </xsl:when> 
          
          <xsl:when test="string-length( $fragment-id ) &gt; 0">
              <xsl:variable name="theDocument_1" select="document( $document-uri,/ )" />
              <xsl:variable name="theFragment_1" select="$theDocument_1//iso:*[@id= $fragment-id]" />
              <xsl:if test="not($theDocument_1)">
				<xsl:message terminate="no">
					<xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">20a</xsl:with-param></xsl:call-template>
					<xsl:value-of select="@href"/>
					<xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">20b</xsl:with-param></xsl:call-template>
				</xsl:message>
			</xsl:if>
              <xsl:if test=" $theFragment_1/self::iso:schema ">
                 <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">21</xsl:with-param></xsl:call-template></xsl:message>
              </xsl:if>
              <xsl:apply-templates select=" $theFragment_1"/>
		   </xsl:when>
		  
		   <xsl:otherwise>
		   	  <!-- Import the top-level element if it is in schematron namespace,
		   	  or its children otherwise, to allow a simple containment mechanism. -->
              <xsl:variable name="theDocument_2" select="document( $document-uri,/ )" />
              <xsl:variable name="theFragment_2" select="$theDocument_2/iso:*" />
              <xsl:variable name="theContainedFragments" select="$theDocument_2/*/iso:*" />
              <xsl:if test="not($theDocument_2)">
				<xsl:message terminate="no">
					<xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">20a</xsl:with-param></xsl:call-template>
					<xsl:value-of select="@href"/>
					<xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">20b</xsl:with-param></xsl:call-template>
				</xsl:message>
			</xsl:if>
              <xsl:if test=" $theFragment_2/self::iso:schema or $theContainedFragments/self::iso:schema">
                 <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">21</xsl:with-param></xsl:call-template></xsl:message>
              </xsl:if>
       		<xsl:apply-templates select="$theFragment_2 | $theContainedFragments "/>
       	   </xsl:otherwise>
       </xsl:choose>
   </xsl:template>
   
   <!-- This is to handle the particular case of including patterns -->  
   <xsl:template match="iso:include" mode="do-all-patterns">
       <xsl:variable name="document-uri" select="substring-before(concat(@href,'#'), '#')"/>
       <xsl:variable name="fragment-id" select="substring-after(@href, '#')"/>
 
       <xsl:choose> 
          
          <xsl:when test="string-length( $document-uri ) = 0 and string-length( $fragment-id ) = 0" >
          	<xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">19</xsl:with-param></xsl:call-template></xsl:message>
          </xsl:when> 
          
          <xsl:when test="string-length( $fragment-id ) &gt; 0">
              <xsl:variable name="theDocument_1" select="document( $document-uri,/ )" />
              <xsl:variable name="theFragment_1" select="$theDocument_1//iso:*[@id= $fragment-id ]" />
              <xsl:if test=" $theFragment_1/self::iso:schema ">
                 <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">21</xsl:with-param></xsl:call-template></xsl:message>
              </xsl:if>
              <xsl:apply-templates select=" $theFragment_1" mode="do-all-patterns"/>
		   </xsl:when>
		  
		   <xsl:otherwise>
		   	  <!-- Import the top-level element if it is in schematron namespace,
		   	  or its children otherwise, to allow a simple containment mechanism. -->
              <xsl:variable name="theDocument_2" select="document( $document-uri,/ )" />
              <xsl:variable name="theFragment_2" select="$theDocument_2/iso:*" />
              <xsl:variable name="theContainedFragments" select="$theDocument_2/*/iso:*" />
              <xsl:if test=" $theFragment_2/self::iso:schema or $theContainedFragments/self::iso:schema">
                 <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">21</xsl:with-param></xsl:call-template></xsl:message>
              </xsl:if>
       		<xsl:apply-templates select="$theFragment_2 | $theContainedFragments "
       		mode="do-all-patterns" />
       	   </xsl:otherwise>
       </xsl:choose>
   </xsl:template>
   
	
	<!-- XSL IMPORT-SCHEMA -->
	<!-- Importing an XSD schema allows the variour type operations to be available. -->
	<xsl:template  match="xsl:import-schema" mode="do-types" >	 
		<xsl:choose>
		  <xsl:when test="ancestor::iso:schema[@queryBinding='xslt2']">
		  	<xsl:copy-of select="." />
		  </xsl:when>
		<xsl:otherwise>
			<xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">22</xsl:with-param></xsl:call-template></xsl:message>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>  
		   
	<!-- swallow -->	   
    <xsl:template match="xsl:import-schema" />
 
	<xsl:template match="iso:import-schema "  >
		<xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">23</xsl:with-param></xsl:call-template></xsl:message>
    </xsl:template>
 
	<!-- ISO LET -->
	<xsl:template match="iso:let" >
	  <xsl:if test="ancestor::iso:schema[@queryBinding='xpath']">
                    <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">24</xsl:with-param></xsl:call-template></xsl:message>
       </xsl:if>
	  <xsl:if test="ancestor::iso:schema[@queryBinding='xpath2']">
                    <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">25</xsl:with-param></xsl:call-template></xsl:message>
       </xsl:if>
       
       <!-- lets at the top-level are implemented as parameters unless they have contents -->
       	<xsl:choose>
       		<xsl:when test="parent::iso:schema">
       			<!-- it is an error to have an empty param/@select because an XPath is expected -->
	      			<xsl:choose>
       				<xsl:when test="@value">
	      				<axsl:param name="{@name}" select="{@value}">
	      		 			<xsl:if test="string-length(@value) &gt; 0">
	      		 				<xsl:attribute name="select"><xsl:value-of select="@value"/></xsl:attribute>
	      		 			</xsl:if>
	      		 		</axsl:param>
	      		 	</xsl:when>
	      		 	<xsl:otherwise>
						<axsl:variable name="{@name}"  >
						  <xsl:copy-of select="child::node()" />
						</axsl:variable>
	      		 	</xsl:otherwise> 
	      		 </xsl:choose>
       		</xsl:when>
       		<xsl:otherwise>
				
       		    <xsl:choose>
       		    	<xsl:when  test="@value">
						<axsl:variable name="{@name}" select="{@value}"/>
					</xsl:when>
					<xsl:otherwise>
						<axsl:variable name="{@name}"  >
						  <xsl:copy-of select="child::node()" />
						</axsl:variable>
				   </xsl:otherwise>
				 </xsl:choose>
				  	
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>	

	<!-- ISO NAME -->
	<xsl:template match="iso:name" mode="text">
	
		<xsl:if test="@path">
			<xsl:call-template name="process-name">
				<xsl:with-param name="name" select="concat('name(',@path,')')"/>
			</xsl:call-template>
		</xsl:if>
		<xsl:if test="not(@path)">
			<xsl:call-template name="process-name">
				<xsl:with-param name="name" select="'name(.)'"/>
			</xsl:call-template>
		</xsl:if>
	    <xsl:call-template name="IamEmpty" />
	</xsl:template>

	<!-- ISO NS -->
	<!-- Namespace handling is XSLT is quite tricky and implementation dependent -->
	<xsl:template match="iso:ns">
 		<xsl:call-template name="handle-namespace" />
	</xsl:template>

    <!-- This template is just to provide the API hook -->
	<xsl:template match="iso:ns"  mode="do-all-patterns" >
               <xsl:if test="not(@uri)">
                    <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">26</xsl:with-param></xsl:call-template></xsl:message>
                </xsl:if>
               <xsl:if test="not(@prefix)">
                    <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">27</xsl:with-param></xsl:call-template></xsl:message>
                </xsl:if>
	        <xsl:call-template name="IamEmpty" />
		<xsl:call-template name="process-ns" >
			<xsl:with-param name="prefix" select="@prefix"/>
			<xsl:with-param name="uri" select="@uri"/>
		</xsl:call-template>
	</xsl:template>

	<!-- ISO P -->
	<xsl:template match="iso:schema/iso:p " mode="do-schema-p" >
		<xsl:call-template name="process-p">
			<xsl:with-param name="class" select="@class"/>
			<xsl:with-param name="icon" select="@icon"/>
			<xsl:with-param name="id" select="@id"/>
			<xsl:with-param name="lang" select="@xml:lang"/>
		</xsl:call-template>
	</xsl:template>
	<xsl:template match="iso:pattern/iso:p " mode="do-pattern-p" >
		<xsl:call-template name="process-p">
			<xsl:with-param name="class" select="@class"/>
			<xsl:with-param name="icon" select="@icon"/>
			<xsl:with-param name="id" select="@id"/>
			<xsl:with-param name="lang" select="@xml:lang"/>
		</xsl:call-template>
	</xsl:template>
	
    <!-- Currently, iso:p in other position are not passed through to the API -->
	<xsl:template match="iso:phase/iso:p" />
	<xsl:template match="iso:p " priority="-1" />
 
	<!-- ISO PATTERN -->
	<xsl:template match="iso:pattern" mode="do-all-patterns">
	<xsl:if test="($phase = '#ALL') 
	or (../iso:phase[@id= $phase]/iso:active[@pattern= current()/@id])">

 		<!-- Extension to allow validation in multiple documents -->  
 		<xsl:choose>
		      	<xsl:when test="string-length(normalize-space(@documents))=0" >
				    <xsl:call-template name="handle-pattern" />
	 	       	</xsl:when>
 		    	<xsl:otherwise>  
 		    	<axsl:variable name="thePath"
 		    		select="{@documents}" 
 		    		as="xs:string*"  /> 
 		    	
				<axsl:for-each  select="$thePath">  
					<axsl:choose>
						<axsl:when test="starts-with( ., 'http:') or starts-with(., 'file:' )
						   or starts-with(., '/')"><!-- try as absolute path -->
		  					<axsl:for-each select="document(.)"> 
		    					<xsl:call-template name="handle-pattern"  />
							</axsl:for-each>
						</axsl:when>
						<axsl:otherwise><!-- is relative path -->
		  					<axsl:for-each select="document(concat( $document-uri , '/../', .))"> 
		    					<xsl:call-template name="handle-pattern"  />
							</axsl:for-each>
						</axsl:otherwise>
				  </axsl:choose>		
				</axsl:for-each>
			</xsl:otherwise>
		</xsl:choose>	
     </xsl:if>

   </xsl:template>
   
   <xsl:template name="handle-pattern">
		<xsl:call-template name="process-pattern">
			<!-- the following select statement assumes that
			@id | iso:title returns node-set in document order:
			we want the title if it is there, otherwise the @id attribute -->
			<xsl:with-param name="name" select="(@id | iso:title )[last()]"/>
			<xsl:with-param name="is-a" select="''"/>
			
					<!-- "Rich" properties -->
					<xsl:with-param name="fpi" select="@fpi"/>
					<xsl:with-param name="icon" select="@icon"/>
					<xsl:with-param name="id" select="@id"/>
					<xsl:with-param name="lang" select="@xml:lang"/>
					<xsl:with-param name="see" select="@see" />
					<xsl:with-param name="space" select="@xml:space" />
		</xsl:call-template>
		<xsl:choose>
		  <!--  Use the key method -->
		  <xsl:when test="$select-contexts='key'">
		    <axsl:apply-templates select="key('M','M{count(preceding-sibling::*)}')" mode="M{count(preceding-sibling::*)}"/>
		  </xsl:when>
		  
		  <!-- Use the // method -->
		  <xsl:when test="$select-contexts='//'">
		    <xsl:choose>
			  	<xsl:when test="@document">
			    	<!-- External document -->
		    		<axsl:for-each select="{@document}">
					<!-- same code as next block, but run from different context -->		    		
		    		<axsl:apply-templates mode="M{count(preceding-sibling::*)}" >
		      			<xsl:attribute name="select"> 
							<xsl:text>//(</xsl:text>
							<xsl:for-each select="iso:rule/@context">
			  					<xsl:text>(</xsl:text>
			  					<xsl:value-of select="."/>
			  					<xsl:text>)</xsl:text>
			  					<xsl:if test="position()!=last()">|</xsl:if>
							</xsl:for-each>
							<xsl:text>)</xsl:text>
							<xsl:if test="$visit-text='false'">[not(self::text())]</xsl:if>
		      			</xsl:attribute>
		    		</axsl:apply-templates>
		    		</axsl:for-each>
		  		</xsl:when>

		  		<xsl:otherwise>
		    		<axsl:apply-templates mode="M{count(preceding-sibling::*)}" >
		      			<xsl:attribute name="select"> 
							<xsl:text>//(</xsl:text>
							<xsl:for-each select="iso:rule/@context">
			  					<xsl:text>(</xsl:text>
			  					<xsl:value-of select="."/>
			  					<xsl:text>)</xsl:text>
			  					<xsl:if test="position()!=last()">|</xsl:if>
							</xsl:for-each>
							<xsl:text>)</xsl:text>
							<xsl:if test="$visit-text='false'">[not(self::text())]</xsl:if>
		      			</xsl:attribute>
		    		</axsl:apply-templates>
		    	</xsl:otherwise>
		    </xsl:choose>
		  </xsl:when>
		  
		  <!-- Use complete tree traversal -->
		  <xsl:when test="@document">
		    <!-- External document -->
		    <axsl:for-each select="{@document}">
		    	<axsl:apply-templates select="." mode="M{count(preceding-sibling::*)}"/>
		    </axsl:for-each>
		  </xsl:when>
		  <xsl:otherwise>
		    <axsl:apply-templates select="/" mode="M{count(preceding-sibling::*)}"/>
		  </xsl:otherwise>
		</xsl:choose>
        <!--/xsl:if-->
	</xsl:template>
	
	<xsl:template match="iso:pattern[@abstract='true']">
    
             <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">28</xsl:with-param></xsl:call-template></xsl:message>
    </xsl:template>

    <!-- Here is the template for the normal case of patterns -->
	<xsl:template match="iso:pattern[not(@abstract='true')]">
    
      <xsl:if test="($phase = '#ALL') 
	          or (../iso:phase[@id= $phase]/iso:active[@pattern= current()/@id])">
		<xsl:text>&#10;&#10;</xsl:text>
		<xsl:comment>PATTERN <xsl:value-of select="@id" /> <xsl:value-of select="iso:title" /> </xsl:comment><xsl:text>&#10;</xsl:text>      
		<xsl:apply-templates />
		
		<!-- DPC select-contexts test -->
		<xsl:if test="not($select-contexts)">
		  <axsl:template match="text()" priority="-1" mode="M{count(preceding-sibling::*)}">
		    <!-- strip characters -->
		  </axsl:template>
		  
		  <!-- DPC introduce context-xpath variable -->
		  <axsl:template match="@*|node()"
				 priority="-2"
				 mode="M{ count(preceding-sibling::*) }">
		    <axsl:apply-templates select="{$context-xpath}" mode="M{count(preceding-sibling::*)}"/>
		  </axsl:template>
		</xsl:if>
      </xsl:if>
	</xsl:template>

	<!-- ISO PHASE -->
	<xsl:template match="iso:phase" >
                <xsl:if test="not(@id)">
                    <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">29</xsl:with-param></xsl:call-template></xsl:message>
                </xsl:if>
		  <xsl:apply-templates/>
	</xsl:template>
	
	
	<!-- PROPERTY Experiemental -->
	<!-- We use a mode here to maintain backwards compatability, instead of adding it
	     to the other mode.
	-->
	<xsl:template match="iso:property" mode="check-property">
              <xsl:if test="not(@id)">
                    <xsl:message>No property found with that ID</xsl:message>
               </xsl:if>
    </xsl:template>
    
    <xsl:template match="iso:property"  >
                <xsl:call-template name="process-property">
                
					<xsl:with-param name="id" select="@id"/>
					
					<xsl:with-param name="name"  select="@name"/>
					<xsl:with-param name="value" select="@value" />
					<xsl:with-param name="contents" select="*|text()" />
				</xsl:call-template>   
            
        </xsl:template>

	<!-- PROPERTIES  experimental extension -->
	<xsl:template match="iso:properties" >
		 <xsl:apply-templates mode="check-properties" select="property" /> 
	</xsl:template>
	
	

	<!-- ISO RULE -->
	<xsl:template match="iso:rule[not(@abstract='true')] ">
                <xsl:if test="not(@context)">
                    <xsl:message ><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">30</xsl:with-param></xsl:call-template></xsl:message>
                  
                    <xsl:message terminate="yes" />
                </xsl:if>
        <xsl:text>&#10;&#10;	</xsl:text>
		<xsl:comment>RULE <xsl:value-of select="@id" /> </xsl:comment><xsl:text>&#10;</xsl:text>   
        <xsl:if test="iso:title">
		    <xsl:comment><xsl:value-of select="iso:title" /></xsl:comment>
		  </xsl:if>
		<!-- DPC select-contexts -->
		<xsl:if test="$select-contexts='key'">
		    <axsl:key name="M"
			      match="{@context}" 
			      use="'M{count(../preceding-sibling::*)}'"/>
		</xsl:if>
   
	
<!-- DPC priorities count up from 1000 not down from 4000 (templates in same priority order as before) -->
		<axsl:template match="{@context}"
		priority="{1000 + count(following-sibling::*)}" mode="M{count(../preceding-sibling::*)}">
		 
			<xsl:call-template name="process-rule">
				<xsl:with-param name="context" select="@context"/>
				
					<xsl:with-param name="properties" select="@properties" />
					
					<!-- "Rich" properties -->
					<xsl:with-param name="fpi" select="@fpi"/>
					<xsl:with-param name="icon" select="@icon"/>
					<xsl:with-param name="id" select="@id"/>
					<xsl:with-param name="lang" select="@xml:lang"/>
					<xsl:with-param name="see" select="@see" />
					<xsl:with-param name="space" select="@xml:space" />
					
					<!-- "Linking" properties -->
					<xsl:with-param name="role" select="@role" />
					<xsl:with-param name="subject" select="@subject" />
			</xsl:call-template>
			 
				
			<xsl:apply-templates/>
			<!-- DPC introduce context-xpath and select-contexts variables -->
			<xsl:if test="not($select-contexts)">
			  <axsl:apply-templates select="{$context-xpath}" mode="M{count(../preceding-sibling::*)}"/>
			</xsl:if>
		</axsl:template>
	</xsl:template>


	<!-- ISO ABSTRACT RULE -->
	<xsl:template match="iso:rule[@abstract='true'] " >
		<xsl:if test=" not(@id)">
                    <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">31</xsl:with-param></xsl:call-template></xsl:message>
                </xsl:if>
 		<xsl:if test="@context">
                    <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">32</xsl:with-param></xsl:call-template></xsl:message>
                </xsl:if>
	</xsl:template>

	<xsl:template match="iso:rule[@abstract='true']"
		mode="extends" >
                <xsl:if test="@context">
                    <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">33</xsl:with-param></xsl:call-template></xsl:message>
                </xsl:if>
			<xsl:apply-templates/>
	</xsl:template>

	<!-- ISO SPAN -->
	<xsl:template match="iso:span" mode="text">
		<xsl:call-template name="process-span">
			<xsl:with-param name="class" select="@class"/>
		</xsl:call-template>
	</xsl:template>

	<!-- ISO TITLE -->
	
	<xsl:template match="iso:schema/iso:title"  priority="1">
	     <xsl:call-template name="process-schema-title" />
	</xsl:template>
 
	
	<xsl:template match="iso:title" >
	     <xsl:call-template name="process-title" />
	</xsl:template>
 

	<!-- ISO VALUE-OF -->
	<xsl:template match="iso:value-of" mode="text" >
        <xsl:if test="not(@select)">
            <xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">34</xsl:with-param></xsl:call-template></xsl:message>
        </xsl:if>
	    <xsl:call-template name="IamEmpty" />
	         
		<xsl:choose>
			<xsl:when test="@select">
				<xsl:call-template name="process-value-of">
					<xsl:with-param name="select" select="@select"/>  
				</xsl:call-template>
			</xsl:when>
			<xsl:otherwise >
				<xsl:call-template name="process-value-of">
					<xsl:with-param name="select" select="'.'"/>
				</xsl:call-template>
			</xsl:otherwise>
        </xsl:choose> 
        
	</xsl:template>


<!-- ============================================================== -->
<!-- DEFAULT TEXT HANDLING  -->
<!-- ============================================================== -->
	<xsl:template match="text()" priority="-1" mode="do-keys">
		<!-- strip characters -->
	</xsl:template>
	<xsl:template match="text()" priority="-1" mode="do-all-patterns">
		<!-- strip characters -->
	</xsl:template>
        <xsl:template match="text()" priority="-1" mode="do-schema-p">
		<!-- strip characters -->
	</xsl:template>
        <xsl:template match="text()" priority="-1" mode="do-pattern-p">
		<!-- strip characters -->
	</xsl:template>
	
	<xsl:template match="text()" priority="-1">
		<!-- Strip characters -->
	</xsl:template>
	
	<xsl:template match="text()" mode="text">
		<xsl:value-of select="."/>
	</xsl:template>

	<xsl:template match="text()" mode="inline-text">
		<xsl:value-of select="."/>
	</xsl:template>

<!-- ============================================================== -->
<!-- UTILITY TEMPLATES -->
<!-- ============================================================== -->
<xsl:template name="IamEmpty">
	<xsl:if test="count( * )">
		<xsl:message>
			<xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">35a</xsl:with-param></xsl:call-template>
			<xsl:value-of select="name(.)"/>
			<xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">35b</xsl:with-param></xsl:call-template>
		</xsl:message>
	</xsl:if>
</xsl:template>

<xsl:template name="diagnosticsSplit">
  <!-- Process at the current point the first of the <diagnostic> elements
       referred to parameter str, and then recurse -->
  <xsl:param name="str"/>
  <xsl:variable name="start">
    <xsl:choose>
      <xsl:when test="contains($str,' ')">
	<xsl:value-of  select="substring-before($str,' ')"/>
      </xsl:when>
      <xsl:otherwise><xsl:value-of select="$str"/></xsl:otherwise>
    </xsl:choose>
  </xsl:variable>

  <xsl:variable name="end">
    <xsl:if test="contains($str,' ')">
      <xsl:value-of select="substring-after($str,' ')"/>
    </xsl:if>
  </xsl:variable>

  <!-- This works with all namespaces -->
  <xsl:if test="not(string-length(normalize-space($start)) = 0)
  		and not(//iso:diagnostic[@id = $start])
		and not(//schold:diagnostic[@id = $start]) 
		and not(//diagnostic[@id = $start])">
	<xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">36a</xsl:with-param></xsl:call-template>
	<xsl:value-of select="string($start)"/>
	<xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">36b</xsl:with-param></xsl:call-template></xsl:message>
  </xsl:if>

  <xsl:if test="string-length(normalize-space($start)) > 0">
     <xsl:text> </xsl:text>
     <xsl:apply-templates 
        select="//iso:diagnostic[@id = $start ]
        	| //schold:diagnostic[@id = $start ] 
            | //diagnostic[@id= $start ]"/>
  </xsl:if>

  <xsl:if test="not($end='')">
    <xsl:call-template name="diagnosticsSplit">
      <xsl:with-param name="str" select="$end"/>
    </xsl:call-template>
  </xsl:if>
</xsl:template>



<xsl:template name="propertiesSplit">
  <!-- Process at the current point the first of the <property> elements
       referred to parameter str, and then recurse -->
  <xsl:param name="str"/>
  <xsl:variable name="start">
    <xsl:choose>
      <xsl:when test="contains($str,' ')">
	<xsl:value-of  select="substring-before($str,' ')"/>
      </xsl:when>
      <xsl:otherwise><xsl:value-of select="$str"/></xsl:otherwise>
    </xsl:choose>
  </xsl:variable>

  <xsl:variable name="end">
    <xsl:if test="contains($str,' ')">
      <xsl:value-of select="substring-after($str,' ')"/>
    </xsl:if>
  </xsl:variable>

  <!-- This works with all namespaces -->
  <xsl:if test="not(string-length(normalize-space($start)) = 0)
  		and not(//iso:property[@id = $start])">
	<xsl:message><xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">36a</xsl:with-param></xsl:call-template>
	<xsl:value-of select="string($start)"/>
	<xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">36b</xsl:with-param></xsl:call-template></xsl:message>
  </xsl:if>

  <xsl:if test="string-length(normalize-space($start)) > 0">
     <xsl:text> </xsl:text>
     <xsl:apply-templates 
        select="//iso:property[@id = $start ] "/>
  </xsl:if>

  <xsl:if test="not($end='')">
    <xsl:call-template name="propertiesSplit">
      <xsl:with-param name="str" select="$end"/>
    </xsl:call-template>
  </xsl:if>
</xsl:template>


<!-- It would be nice to use this but xsl:namespace does not
  allow a fallback -->
<!--xsl:template name="handle-namespace" version="2.0">
   <xsl:namespace name="{@prefix}" select="@uri">
</xsl:template-->

<xsl:template name="handle-namespace">
       <!-- experimental code from http://eccnet.eccnet.com/pipermail/schematron-love-in/2006-June/000104.html -->
       <!-- Handle namespaces differently for exslt systems,   and default, only using XSLT1 syntax -->
       <!-- For more info see  http://fgeorges.blogspot.com/2007/01/creating-namespace-nodes-in-xslt-10.html -->
       <xsl:choose>
   	<!-- The following code workds for XSLT2 -->
         <xsl:when test="element-available('xsl:namespace')">
             <xsl:namespace name="{@prefix}" select="@uri" />
	 </xsl:when>
 
	 <xsl:when use-when="not(element-available('xsl:namespace'))" 
		   test="function-available('exsl:node-set')">
           <xsl:variable name="ns-dummy-elements">
             <xsl:element name="{@prefix}:dummy" namespace="{@uri}"/>
           </xsl:variable>
       	   <xsl:variable name="p" select="@prefix"/>
           <xsl:copy-of select="exsl:node-set($ns-dummy-elements)
                                  /*/namespace::*[local-name()=$p]"/>
         </xsl:when>

	<!-- end XSLT2 code -->

        
        <xsl:when test="@prefix = 'xsl' ">
           <!-- Do not generate dummy attributes with the xsl: prefix, as these
                are errors against XSLT, because we presume that the output
                stylesheet uses the xsl prefix. In any case, there would already
                be a namespace declaration for the XSLT namespace generated
                automatically, presumably using "xsl:".
           -->
        </xsl:when>
        
        <xsl:when test="@uri = 'http://www.w3.org/1999/XSL/Transform'">
          <xsl:message terminate="yes">
            <xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">37a</xsl:with-param></xsl:call-template>
            <xsl:value-of select="system-property('xsl:vendor')"/>
            <xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">37b</xsl:with-param></xsl:call-template>
          </xsl:message>
        </xsl:when>

        <xsl:otherwise>
          <xsl:attribute name="{concat(@prefix,':dummy-for-xmlns')}" namespace="{@uri}" />
           
        </xsl:otherwise>
      </xsl:choose>


</xsl:template>

<!-- ============================================================== -->
<!-- UNEXPECTED ELEMENTS -->
<!-- ============================================================== -->

	<xsl:template match="iso:*"  priority="-2">
	   <xsl:message>
			<xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">38a</xsl:with-param></xsl:call-template>
			<xsl:value-of select="name(.)"/>
			<xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">38b</xsl:with-param></xsl:call-template>
		</xsl:message>
	</xsl:template>
	
	
	<!-- Swallow old namespace elements: there is an upfront test for them elsewhere -->
	<xsl:template match="schold:*"  priority="-2" />
	 
	<xsl:template match="*"  priority="-3">
	    <xsl:choose>
	       <xsl:when test=" $allow-foreign = 'false' ">
				<xsl:message>
					<xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">39a</xsl:with-param></xsl:call-template>
					<xsl:value-of select="name(.)"/>
					<xsl:call-template name="outputLocalizedMessage" ><xsl:with-param name="number">39b</xsl:with-param></xsl:call-template>
				</xsl:message>
			</xsl:when>
			<xsl:otherwise>
				<xsl:copy-of select="." />
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>
	
	<xsl:template match="iso:*" mode="text" priority="-2" />
	<xsl:template match="*" mode="text" priority="-3">
	    <xsl:if test=" not( $allow-foreign = 'false') "> 
				<xsl:copy-of select="." />
		</xsl:if>
	</xsl:template>

<!-- ============================================================== -->
<!-- DEFAULT NAMED TEMPLATES -->
<!-- These are the actions that are performed unless overridden -->
<!-- ============================================================== -->
 
	<xsl:template name="process-prolog"/>
	<!-- no params -->

	<xsl:template name="process-root">
		<xsl:param name="contents"/>
		<xsl:param name="id" />
		<xsl:param name="version" />
		<xsl:param name="schemaVersion" />
		<xsl:param name="queryBinding" />
		<xsl:param name="title" />


		<!-- "Rich" parameters -->
		<xsl:param name="fpi" />
		<xsl:param name="icon" />
		<xsl:param name="lang" />
		<xsl:param name="see" />
		<xsl:param name="space" />

		<xsl:copy-of select="$contents"/>
	</xsl:template>

	<xsl:template name="process-assert">

		<xsl:param name="test"/>
		<xsl:param name="diagnostics" />
		<xsl:param name="id" />
		<xsl:param name="flag" />
		<xsl:param name="properties" />

           	<!-- "Linkable" parameters -->
		<xsl:param name="role"/>
		<xsl:param name="subject"/>

		<!-- "Rich" parameters -->
		<xsl:param name="fpi" />
		<xsl:param name="icon" />
		<xsl:param name="lang" />
		<xsl:param name="see" />
		<xsl:param name="space" />


		<xsl:call-template name="process-message">
			<xsl:with-param name="pattern" select="$test"/>
			<xsl:with-param name="role" select="$role"/>
		</xsl:call-template>
		
		<xsl:if test=" $terminate = 'yes' or $terminate = 'true' ">
		   <axsl:message terminate="yes">TERMINATING</axsl:message>
		</xsl:if>
	    <xsl:if test=" $terminate = 'assert' ">
		   <axsl:message terminate="yes">TERMINATING</axsl:message>
		</xsl:if>
		
	</xsl:template>

	<xsl:template name="process-report">
		<xsl:param name="test"/>
		<xsl:param name="diagnostics" />
		<xsl:param name="id" />
		<xsl:param name="flag" />
		<xsl:param name="properties" />

           	<!-- "Linkable" parameters -->
		<xsl:param name="role"/>
		<xsl:param name="subject"/>

		<!-- "Rich" parameters -->
		<xsl:param name="fpi" />
		<xsl:param name="icon" /> 
		<xsl:param name="lang" />
		<xsl:param name="see" />
		<xsl:param name="space" />

		<xsl:call-template name="process-message">
			<xsl:with-param name="pattern" select="$test"/>
			<xsl:with-param name="role" select="$role"/>
		</xsl:call-template>
				
		<xsl:if test=" $terminate = 'yes' or $terminate = 'true'  ">
		   <axsl:message terminate="yes">TERMINATING</axsl:message>
		</xsl:if>
	</xsl:template>

	<xsl:template name="process-diagnostic">
		<xsl:param name="id" />

		<!-- "Rich" parameters -->
		<xsl:param name="fpi" />
		<xsl:param name="icon" />
		<xsl:param name="lang" />
		<xsl:param name="see" />
		<xsl:param name="space" />
		
	    <!-- We generate too much whitespace rather than risking concatenation -->
		<axsl:text> </axsl:text>
		<xsl:apply-templates mode="text"/>
		<axsl:text> </axsl:text>
	</xsl:template>

	<xsl:template name="process-dir">
      	<xsl:param name="value" />

	    <!-- We generate too much whitespace rather than risking concatenation -->
		<axsl:text> </axsl:text>
		<xsl:apply-templates mode="inline-text"/>
		<axsl:text> </axsl:text>
	</xsl:template>

	<xsl:template name="process-emph"> 
	    <!-- We generate too much whitespace rather than risking concatenation -->
		<axsl:text> </axsl:text>
		<xsl:apply-templates mode="inline-text"/>
		<axsl:text> </axsl:text>
	</xsl:template>
	
	<xsl:template name="process-name">
		<xsl:param name="name"/>
		
		<!-- We generate too much whitespace rather than risking concatenation -->
		<axsl:text> </axsl:text>
		<axsl:value-of select="{$name}"/>
		<axsl:text> </axsl:text>
		
    </xsl:template>

	<xsl:template name="process-ns" >
	<!-- Note that process-ns is for reporting. The iso:ns elements are 
	     independently used in the iso:schema template to provide namespace bindings -->
		<xsl:param name="prefix"/>
		<xsl:param name="uri" />
      </xsl:template>

	<xsl:template name="process-p">
		<xsl:param name="id" />
		<xsl:param name="class" />
		<xsl:param name="icon" />
		<xsl:param name="lang" />
      </xsl:template>

	<xsl:template name="process-pattern">
		<xsl:param name="id" />
		<xsl:param name="name" />
		<xsl:param name="is-a" />

		<!-- "Rich" parameters -->
		<xsl:param name="fpi" />
		<xsl:param name="icon" />
		<xsl:param name="lang" />
		<xsl:param name="see" />
		<xsl:param name="space" />
      </xsl:template>
      

	<xsl:template name="process-rule">
		<xsl:param name="context" />

		<xsl:param name="id" />
		<xsl:param name="flag" />
		<xsl:param name="properties" />

           	<!-- "Linkable" parameters -->
		<xsl:param name="role"/>
		<xsl:param name="subject"/>
  
		<!-- "Rich" parameters -->
		<xsl:param name="fpi" />
		<xsl:param name="icon" />
		<xsl:param name="lang" />
		<xsl:param name="see" />
		<xsl:param name="space" />
      </xsl:template>

	<xsl:template name="process-span" >
		<xsl:param name="class" />

	    <!-- We generate too much whitespace rather than risking concatenation -->
		<axsl:text> </axsl:text>
		<xsl:apply-templates mode="inline-text"/>
		<axsl:text> </axsl:text>		
	</xsl:template>

	<xsl:template name="process-title" >
		<xsl:param name="class" />
	   <xsl:call-template name="process-p">
	      <xsl:with-param  name="class">title</xsl:with-param>
	   </xsl:call-template>
	</xsl:template>
		
	<xsl:template name="process-schema-title" >
		<xsl:param name="class" />
	   <xsl:call-template name="process-title">
	      <xsl:with-param  name="class">schema-title</xsl:with-param>
	   </xsl:call-template>
	</xsl:template>

	<xsl:template name="process-value-of">
		<xsl:param name="select"/>
		
	    <!-- We generate too much whitespace rather than risking concatenation -->
		<axsl:text> </axsl:text>
		<axsl:value-of select="{$select}"/>
		<axsl:text> </axsl:text>
	</xsl:template>

	<!-- default output action: the simplest customization is to just override this -->
	<xsl:template name="process-message">
		<xsl:param name="pattern" />
            <xsl:param name="role" />

		<xsl:apply-templates mode="text"/>	
		 <xsl:if test=" $message-newline = 'true'" >
			<axsl:value-of  select="string('&#10;')"/>
		</xsl:if>
		
	</xsl:template>
	
	
	<!-- ===================================================== -->
	<!-- Extension API: default rules 			               -->
	<!-- This allows the transmission of extra attributes on   -->
	<!-- rules, asserts, reports, diagnostics.                 -->
	<!-- ===================================================== -->
	
 

	<xsl:template name="process-property">
		<xsl:param name="id" />
		
		<xsl:param name="name"/>
		<xsl:param name="value"/>
		<xsl:param name="contents"/>
		  
	</xsl:template>
	
	
	<!-- ===================================================== -->
	<!-- Localization 						                  -->
	<!-- ===================================================== -->
	<!--
		All messages generated by the skeleton during processing are localized.
		(This does not apply to the text that comes from Schematron schemas
		themselves, of course. Nor does it apply to messages in metastylesheets.)
		
		Stylesheets have a parameter $langCode which can be used to select the
		language code (e.g. from the command line)
		
		The default value of $langCode is "default". When this is used, the
		message text is taken from the strings below. We use XHTML, to provide
		the namespace. 
		
		If the $langCode is somethign else, then the XSLT engine will try to
		find a file called  sch-messages-$langCode.xhtml in the same directory
		as this stylesheet. Expect a fatal error if the file does not exist.
		
		The file should contain XHTML elements, with the text translated.
		The strings are located by using ids on each xhtml:p element.
		The ids are formed by sch-message-$number-$langCode such as  
		sch-message-1-en
		
		If there is no match in a localization file for a message, then the
		default will be used. This allows this XSLT to be developed with new
		messages added without requiring that any localization files be updated.
		
		In many cases, there are actually two localization strings per message.
		This happens whenever a message has an embedded value that is dynamically
		generated (using <value-of>). Having two strings, preceding and following,
		allows the translator to make idiomatic error messages. When there are
		two message for a single message, they have numbers like 30a and 30b: 
		translators should check the reference to them in the XSLT above to
		see what the dynamically generated information is.  
	-->
	<xsl:template name="outputLocalizedMessage">
		<xsl:param name="number" />  
		 
		<xsl:choose>
		   <xsl:when test="string-length( $langCode ) = 0 or $langCode = 'default'" > 	   	 
				<xsl:value-of select='document("")//xhtml:p[@id=concat("sch-message-", $number)]/text()' />
			</xsl:when>
			<xsl:otherwise>
				<xsl:variable name="localizationDocumentFilename" >
					<xsl:value-of select="concat('sch-messages-', $langCode, '.xhtml')" />
				</xsl:variable>
				<xsl:variable name="theLocalizedMessage" >
					<xsl:value-of select=
				'document( $localizationDocumentFilename, /)//xhtml:p[@id=concat("sch-message-", $number, "-", $langCode)]/text()' />
				</xsl:variable>
				
				<xsl:choose>
					<!-- if we found any external message with that id, use it -->
					<xsl:when test=" string-length($theLocalizedMessage) &gt; 0">
						<xsl:value-of select="$theLocalizedMessage" />
					</xsl:when>
					<xsl:otherwise>
						<!-- otherwise use the default strings -->		
						<xsl:value-of select='document("")//xhtml:p[@id=concat("sch-message-", $number)]/text()' />
					</xsl:otherwise>
				</xsl:choose>	
				
				 
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>
	
<xhtml:div class="ErrorMessages">	
	<!-- Where the error message contains dynamic information, the message has been split into an "a" and a "b" section.
	     This has been done even when the English does not require it, in order to accomodate different language grammars
	     that might position the dynamic information differently.
	-->
	<xhtml:p id="sch-message-1">Schema error: Schematron elements in old and new namespaces found</xhtml:p>
	<xhtml:p id="sch-message-2">Schema error: in the queryBinding attribute, use 'xslt'</xhtml:p>
	<xhtml:p id="sch-message-3a">Fail: This implementation of ISO Schematron does not work with schemas using the query language </xhtml:p>
	<xhtml:p id="sch-message-3b"/>
	<xhtml:p id="sch-message-4a">Phase Error: no phase has been defined with name </xhtml:p>
	<xhtml:p id="sch-message-4b" />
	<xhtml:p id="sch-message-5">Markup Error: no pattern attribute in &lt;active></xhtml:p>
	<xhtml:p id="sch-message-6a">Reference Error: the pattern  "</xhtml:p>
	<xhtml:p id="sch-message-6b">" has been activated but is not declared</xhtml:p>
	<xhtml:p id="sch-message-7">Markup Error: no test attribute in &lt;assert</xhtml:p>
	<xhtml:p id="sch-message-8">Markup Error: no test attribute in &lt;report></xhtml:p>
	<xhtml:p id="sch-message-9">Markup Error: no id attribute in &lt;diagnostic></xhtml:p>
	<xhtml:p id="sch-message-10">Markup Error: no rule attribute in &lt;extends></xhtml:p>				   
	<xhtml:p id="sch-message-11a">Reference Error: the abstract rule  "</xhtml:p>
	<xhtml:p id="sch-message-11b">" has been referenced but is not declared</xhtml:p>				
	<xhtml:p id="sch-message-12">Markup Error: no name attribute in &lt;key></xhtml:p>
	<xhtml:p id="sch-message-13">Markup Error: no path or use attribute in &lt;key></xhtml:p>
	<xhtml:p id="sch-message-14">Markup Error: no path or use attribute in &lt;key></xhtml:p>
	<xhtml:p id="sch-message-15">Schema error: The key element is not in the ISO Schematron namespace. Use the XSLT namespace.</xhtml:p>
	<xhtml:p id="sch-message-16">Markup Error: no name attribute in &lt;function></xhtml:p>
	<xhtml:p id="sch-message-17">Schema error: The function element is not in the ISO Schematron namespace. Use the XSLT namespace.</xhtml:p>
	<xhtml:p id="sch-message-18">Schema error: Empty href= attribute for include directive.</xhtml:p>
	<xhtml:p id="sch-message-19">Error: Impossible URL in Schematron include</xhtml:p>
	<xhtml:p id="sch-message-20a">Unable to open referenced included file: </xhtml:p>
	<xhtml:p id="sch-message-20b" />
	<xhtml:p id="sch-message-21">Schema error: Use include to include fragments, not a whole schema</xhtml:p>
	<xhtml:p id="sch-message-22">Schema error: XSD schemas may only be imported if you are using the 'xslt2' query language binding</xhtml:p>
	<xhtml:p id="sch-message-23">Schema error: The import-schema element is not available in the ISO Schematron namespace. Use the XSLT namespace.</xhtml:p>
	<xhtml:p id="sch-message-24">Warning: Variables should not be used with the "xpath" query language binding.</xhtml:p>
	<xhtml:p id="sch-message-25">Warning: Variables should not be used with the "xpath2" query language binding.</xhtml:p>
	<xhtml:p id="sch-message-26">Markup Error: no uri attribute in &lt;ns></xhtml:p>
	<xhtml:p id="sch-message-27">Markup Error: no prefix attribute in &lt;ns></xhtml:p>
	<xhtml:p id="sch-message-28">Schema implementation error: This schema has abstract patterns, yet they are supposed to be preprocessed out already</xhtml:p>
    <xhtml:p id="sch-message-29">Markup Error: no id attribute in &lt;phase></xhtml:p>
    <xhtml:p id="sch-message-30">Markup Error: no context attribute in &lt;rule></xhtml:p>
    <xhtml:p id="sch-message-31">Markup Error: no id attribute on abstract &lt;rule></xhtml:p>
    <xhtml:p id="sch-message-32">Markup Error: (2) context attribute on abstract &lt;rule></xhtml:p>
    <xhtml:p id="sch-message-33">Markup Error: context attribute on abstract &lt;rule></xhtml:p>
    <xhtml:p id="sch-message-34">Markup Error: no select attribute in &lt;value-of></xhtml:p>
    <xhtml:p id="sch-message-35a">Warning: </xhtml:p>
	<xhtml:p id="sch-message-35b"> must not contain any child elements</xhtml:p>
    <xhtml:p id="sch-message-36a">Reference error: A diagnostic "</xhtml:p>
	<xhtml:p id="sch-message-36b">" has been referenced but is not declared</xhtml:p>
	<xhtml:p id="sch-message-37a">Using the XSLT namespace with a prefix other than "xsl" in Schematron rules is not supported in this processor:</xhtml:p>
    <xhtml:p id="sch-message-37b" />
    <xhtml:p id="sch-message-38a">Error: unrecognized element in ISO Schematron namespace: check spelling and capitalization</xhtml:p>
	<xhtml:p id="sch-message-38b" />
	<xhtml:p id="sch-message-39a">Warning: unrecognized element </xhtml:p>
	<xhtml:p id="sch-message-39b" />
 </xhtml:div>
</xsl:stylesheet>


