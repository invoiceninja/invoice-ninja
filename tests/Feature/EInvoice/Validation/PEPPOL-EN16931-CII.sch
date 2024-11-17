<?xml version="1.0" encoding="UTF-8"?>
<!--
This schematron uses business terms defined the CEN/EN16931-1 and is reproduced with permission from CEN. CEN bears no liability from the use of the content and implementation of this schematron and gives no warranties expressed or implied for any purpose.

Last update: 2024 May release 3.0.17.
 -->
<schema xmlns="http://purl.oclc.org/dsdl/schematron" xmlns:u="utils" schemaVersion="iso" queryBinding="xslt2">
  <title>Rules for PEPPOL BIS 3.0 Billing</title>
  <ns uri="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100" prefix="rsm"/>
  <ns uri="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100" prefix="udt"/>
  <ns uri="urn:un:unece:uncefact:data:standard:QualifiedDataType:100" prefix="qdt"/>
  <ns uri="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100" prefix="ram"/>
  <ns uri="http://www.w3.org/2001/XMLSchema" prefix="xs"/>
  <ns uri="utils" prefix="u"/>
  <!-- Parameters -->
  <let name="profile" value="
            if (/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:BusinessProcessSpecifiedDocumentContextParameter and matches(normalize-space(/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID), 'urn:fdc:peppol.eu:2017:poacc:billing:([0-9]{2}):1.0')) then
                tokenize(normalize-space(/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID), ':')[7]
            else
                'Unknown'"/>
  <let name="supplierCountry" value="
            if (/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction[1]/ram:ApplicableHeaderTradeAgreement[1]/ram:SellerTradeParty[1]/ram:SpecifiedTaxRegistration[ram:ID/@schemeID = 'VAT']/substring(ram:ID, 1, 2)) then
                upper-case(normalize-space(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction[1]/ram:ApplicableHeaderTradeAgreement[1]/ram:SellerTradeParty[1]/ram:SpecifiedTaxRegistration[ram:ID/@schemeID = 'VAT']/substring(ram:ID, 1, 2)))
            else
                if (/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTaxRepresentativeTradeParty/ram:SpecifiedTaxRegistration[ram:ID/@schemeID = 'VAT']/substring(ram:ID, 1, 2)) then
                    upper-case(normalize-space(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTaxRepresentativeTradeParty/ram:SpecifiedTaxRegistration[ram:ID/@schemeID = 'VAT']/substring(ram:ID, 1, 2)))
                else
                    if (/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:PostalTradeAddress/ram:CountryID) then
                        upper-case(normalize-space(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:PostalTradeAddress/ram:CountryID))
                    else
                        'XX'"/>
  <!-- -->
  <let name="documentCurrencyCode" value="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:InvoiceCurrencyCode"/>
  <let name="taxCurrencyCode" value="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:TaxCurrencyCode"/>
  <!-- Functions -->
  <function xmlns="http://www.w3.org/1999/XSL/Transform" name="u:gln" as="xs:boolean">
    <param name="val"/>
    <variable name="length" select="string-length($val) - 1"/>
    <variable name="digits" select="reverse(for $i in string-to-codepoints(substring($val, 0, $length + 1)) return $i - 48)"/>
    <variable name="weightedSum" select="sum(for $i in (0 to $length - 1) return $digits[$i + 1] * (1 + ((($i + 1) mod 2) * 2)))"/>
    <value-of select="(10 - ($weightedSum mod 10)) mod 10 = number(substring($val, $length + 1, 1))"/>
  </function>
  <function xmlns="http://www.w3.org/1999/XSL/Transform" name="u:slack" as="xs:boolean">
    <param name="exp" as="xs:decimal"/>
    <param name="val" as="xs:decimal"/>
    <param name="slack" as="xs:decimal"/>
    <value-of select="xs:decimal($exp + $slack) &gt;= $val and xs:decimal($exp - $slack) &lt;= $val"/>
  </function>
  <function xmlns="http://www.w3.org/1999/XSL/Transform" name="u:mod11" as="xs:boolean">
    <param name="val"/>
    <variable name="length" select="string-length($val) - 1"/>
    <variable name="digits" select="reverse(for $i in string-to-codepoints(substring($val, 0, $length + 1)) return $i - 48)"/>
    <variable name="weightedSum" select="sum(for $i in (0 to $length - 1) return $digits[$i + 1] * (($i mod 6) + 2))"/>
    <value-of select="number($val) &gt; 0 and (11 - ($weightedSum mod 11)) mod 11 = number(substring($val, $length + 1, 1))"/>
  </function>
	<function xmlns="http://www.w3.org/1999/XSL/Transform" name="u:mod97-0208" as="xs:boolean">
		<param name="val"/>
		<variable name="checkdigits" select="substring($val,9,2)"/>
		<variable name="calculated_digits" select="xs:string(97 - (xs:integer(substring($val,1,8)) mod 97))"/>
		<value-of select="number($checkdigits) = number($calculated_digits)"/>
	</function>
<function name="u:checkCodiceIPA" as="xs:boolean" xmlns="http://www.w3.org/1999/XSL/Transform">
    <param name="arg" as="xs:string?"/>
    <variable name="allowed-characters">ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789</variable>
    <sequence select="if ( (string-length(translate($arg, $allowed-characters, '')) = 0) and (string-length($arg) = 6) ) then true() else false()"/>
  </function>
  <function name="u:checkCF" as="xs:boolean" xmlns="http://www.w3.org/1999/XSL/Transform">
    <param name="arg" as="xs:string?"/>
    <sequence select="
		if ( (string-length($arg) = 16) or (string-length($arg) = 11) )
		then
		(
			if ((string-length($arg) = 16))
			then
			(
				if (u:checkCF16($arg))
				then
				(
					true()
				)
				else
				(
					false()
				)
			)
			else
			(
				if(($arg castable as xs:integer)) then true() else false()

			)
		)
		else
		(
			false()
		)
		"/>
  </function>
  <function name="u:checkCF16" as="xs:boolean" xmlns="http://www.w3.org/1999/XSL/Transform">
    <param name="arg" as="xs:string?"/>
    <variable name="allowed-characters">ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz</variable>
    <sequence select="
				if ( 	(string-length(translate(substring($arg,1,6), $allowed-characters, '')) = 0) and
						(substring($arg,7,2) castable as xs:integer) and
						(string-length(translate(substring($arg,9,1), $allowed-characters, '')) = 0) and
						(substring($arg,10,2) castable as xs:integer) and
						(substring($arg,12,3) castable as xs:string) and
						(substring($arg,15,1) castable as xs:integer) and
						(string-length(translate(substring($arg,16,1), $allowed-characters, '')) = 0)
					)
				then true()
				else false()
				"/>
  </function>
  <function name="u:checkPIVAseIT" as="xs:boolean" xmlns="http://www.w3.org/1999/XSL/Transform">
    <param name="arg" as="xs:string"/>
    <variable name="paese" select="substring($arg,1,2)"/>
    <variable name="codice" select="substring($arg,3)"/>
    <sequence select="

			if ( $paese = 'IT' or $paese = 'it' )
			then
			(
				if ( ( string-length($codice) = 11 ) and ( if (u:checkPIVA($codice)!=0) then false() else true() ))
				then
				(
					true()
				)
				else
				(
					false()
				)
			)
			else
			(
				true()
			)

		"/>
  </function>
  <function name="u:checkPIVA" as="xs:integer" xmlns="http://www.w3.org/1999/XSL/Transform">
    <param name="arg" as="xs:string?"/>
    <sequence select="
				if (not($arg castable as xs:integer))
					then 1
					else ( u:addPIVA($arg,xs:integer(0)) mod 10 )"/>
  </function>
  <function name="u:addPIVA" as="xs:integer" xmlns="http://www.w3.org/1999/XSL/Transform">
    <param name="arg" as="xs:string"/>
    <param name="pari" as="xs:integer"/>
    <variable name="tappo" select="if (not($arg castable as xs:integer)) then 0 else 1"/>
    <variable name="mapper" select="if ($tappo = 0) then 0 else
																		( if ($pari = 1)
																			then ( xs:integer(substring('0246813579', ( xs:integer(substring($arg,1,1)) +1 ) ,1)) )
																			else ( xs:integer(substring($arg,1,1) ) )
																		)"/>
    <sequence select="if ($tappo = 0) then $mapper else ( xs:integer($mapper) + u:addPIVA(substring(xs:string($arg),2), (if($pari=0) then 1 else 0) ) )"/>
  </function>
  <function xmlns="http://www.w3.org/1999/XSL/Transform" name="u:abn" as="xs:boolean">
    <param name="val"/>
    <value-of select="(
((string-to-codepoints(substring($val,1,1)) - 49) * 10) +
((string-to-codepoints(substring($val,2,1)) - 48) * 1) +
((string-to-codepoints(substring($val,3,1)) - 48) * 3) +
((string-to-codepoints(substring($val,4,1)) - 48) * 5) +
((string-to-codepoints(substring($val,5,1)) - 48) * 7) +
((string-to-codepoints(substring($val,6,1)) - 48) * 9) +
((string-to-codepoints(substring($val,7,1)) - 48) * 11) +
((string-to-codepoints(substring($val,8,1)) - 48) * 13) +
((string-to-codepoints(substring($val,9,1)) - 48) * 15) +
((string-to-codepoints(substring($val,10,1)) - 48) * 17) +
((string-to-codepoints(substring($val,11,1)) - 48) * 19)) mod 89 = 0
"/>
  </function>
  
    <!-- Function for Swedish organisation numbers (0007) -->
  <function xmlns="http://www.w3.org/1999/XSL/Transform" name="u:checkSEOrgnr" as="xs:boolean">
    <param name="number" as="xs:string"/>
	<choose>
		<!-- Check if input is numeric -->
		<when test="not(matches($number, '^\d+$'))">
			<sequence select="false()"/>
		</when>
		<otherwise>
			<!-- verify the check number of the provided identifier according to the Luhn algorithm-->
			<variable name="mainPart" select="substring($number, 1, 9)"/>
			<variable name="checkDigit" select="substring($number, 10, 1)"/>
			<variable name="sum" as="xs:integer">
			  <value-of select="sum(
						for $pos in 1 to string-length($mainPart) return 
							if ($pos mod 2 = 1) 
							then (number(substring($mainPart, string-length($mainPart) - $pos + 1, 1)) * 2) mod 10 + 
								 (number(substring($mainPart, string-length($mainPart) - $pos + 1, 1)) * 2) idiv 10 
							else number(substring($mainPart, string-length($mainPart) - $pos + 1, 1))
					)"/>
			</variable>
			<variable name="calculatedCheckDigit" select="(10 - $sum mod 10) mod 10"/>
			<sequence select="$calculatedCheckDigit = number($checkDigit)"/>
		</otherwise>
	</choose>
  </function>
  
  <pattern>
    <rule context="rsm:ExchangedDocumentContext">
      <assert id="PEPPOL-EN16931-R001" test="ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID" flag="fatal">Business process MUST be provided.</assert>
      <assert id="PEPPOL-EN16931-R007" test="$profile != 'Unknown'" flag="fatal">Business process MUST be in the format 'urn:fdc:peppol.eu:2017:poacc:billing:NN:1.0' where NN indicates the process number.</assert>
      <assert id="PEPPOL-EN16931-R004" test="starts-with(normalize-space(ram:GuidelineSpecifiedDocumentContextParameter/ram:ID/text()), 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0')" flag="fatal">Specification identifier MUST have the value 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0'.</assert>
    </rule>
    <rule context="ram:ApplicableHeaderTradeAgreement">
      <assert id="PEPPOL-EN16931-R003" test="ram:BuyerReference or ram:BuyerOrderReferencedDocument/ram:IssuerAssignedID" flag="fatal">A buyer reference or purchase order reference MUST be provided.</assert>
      <assert id="PEPPOL-EN16931-R006" test="count(ram:AdditionalReferencedDocument[ram:TypeCode='130']) &lt;=1" flag="fatal">Only one invoiced object is allowed on document level</assert>
      <assert id="PEPPOL-EN16931-R080" test="count(ram:AdditionalReferencedDocument[ram:TypeCode='50']) &lt;=1" flag="fatal">Only one project reference is allowed on document level</assert>
    </rule>
    <rule context="ram:ApplicableHeaderTradeSettlement">
      <assert id="PEPPOL-EN16931-R005" test="not(ram:TaxCurrencyCode) or normalize-space(ram:TaxCurrencyCode/text()) != normalize-space(ram:InvoiceCurrencyCode/text())" flag="fatal">VAT accounting currency code MUST be different from invoice currency code when provided.</assert>
      <assert id="PEPPOL-EN16931-R053" test="count(ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID = $documentCurrencyCode]) = 1" flag="fatal">Only one tax total with tax subtotals MUST be provided.</assert>
      <assert id="PEPPOL-EN16931-R054" test="
                    count(ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID != $documentCurrencyCode]) = (if (ram:TaxCurrencyCode) then
                        1
                    else
                        0)" flag="fatal">Only one tax total without tax subtotals MUST be provided when tax currency code is provided.</assert>
      <assert id="PEPPOL-EN16931-R055" test="not(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:TaxCurrencyCode) or (ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID = $taxCurrencyCode] &lt; 0 and ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID = $documentCurrencyCode] &lt; 0) or (ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID = $taxCurrencyCode] &gt;= 0 and ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID = $documentCurrencyCode] &gt;= 0)" flag="fatal">Invoice total VAT amount and Invoice total VAT amount in accounting currency MUST have the same operational sign</assert>
    </rule>
    <!-- PEPPOL-EN16931-R051 is obsolete in CII. -->
    <rule context="rsm:ExchangedDocument">
      <assert id="PEPPOL-EN16931-R002" test="count(ram:IncludedNote) &lt;= 1 and not(ram:IncludedNote/ram:SubjectCode)" flag="fatal">No more than one note is allowed on document level.</assert>
    </rule>
    <rule context="ram:BuyerTradeParty">
      <assert id="PEPPOL-EN16931-R010" test="ram:URIUniversalCommunication/ram:URIID" flag="fatal">Buyer electronic address MUST be provided</assert>
    </rule>
    <rule context="ram:SellerTradeParty">
      <assert id="PEPPOL-EN16931-R020" test="ram:URIUniversalCommunication/ram:URIID" flag="fatal">Seller electronic address MUST be provided</assert>
    </rule>
    <rule context="ram:SpecifiedTradeAllowanceCharge[ram:CalculationPercent and not(ram:BasisAmount)]">
      <assert id="PEPPOL-EN16931-R041" test="false()" flag="fatal">Allowance/charge base
                amount MUST be provided when allowance/charge percentage is provided.</assert>
    </rule>
    <rule context="ram:SpecifiedTradeAllowanceCharge[not(ram:CalculationPercent) and ram:BasisAmount]">
      <assert id="PEPPOL-EN16931-R042" test="false()" flag="fatal">Allowance/charge percentage
                MUST be provided when allowance/charge base amount is provided.</assert>
    </rule>
    <rule context="ram:SpecifiedTradeAllowanceCharge">
      <assert id="PEPPOL-EN16931-R040" test="
                    not(ram:CalculationPercent and ram:BasisAmount) or u:slack(if (ram:ActualAmount) then
                        ram:ActualAmount
                    else
                        0, (xs:decimal(ram:BasisAmount) * xs:decimal(ram:CalculationPercent)) div 100, 0.02)" flag="fatal">Allowance/charge amount must equal base amount * percentage/100 if base amount and percentage exists</assert>
      <assert id="PEPPOL-EN16931-R043" test="normalize-space(ram:ChargeIndicator/udt:Indicator/text()) = 'true' or normalize-space(ram:ChargeIndicator/udt:Indicator/text()) = 'false'" flag="fatal">Allowance/charge ChargeIndicator value MUST equal 'true' or 'false'</assert>
    </rule>
    <rule context="ram:AppliedTradeAllowanceCharge">
      <assert id="PEPPOL-EN16931-R043" test="normalize-space(ram:ChargeIndicator/udt:Indicator/text()) = 'true' or normalize-space(ram:ChargeIndicator/udt:Indicator/text()) = 'false'" flag="fatal">Allowance/charge ChargeIndicator value MUST equal 'true' or 'false'</assert>
    </rule>
    <rule context="
                ram:SpecifiedTradeSettlementPaymentMeans[some $code in tokenize('49 59', '\s')
                    satisfies normalize-space(ram:TypeCode) = $code]">
      <assert id="PEPPOL-EN16931-R061" test="../ram:SpecifiedTradePaymentTerms/ram:DirectDebitMandateID" flag="fatal">Mandate reference MUST be provided for direct debit.</assert>
    </rule>
    <rule context="rsm:SupplyChainTradeTransaction[ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:StartDateTime]/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:BillingSpecifiedPeriod/ram:StartDateTime">
      <assert id="PEPPOL-EN16931-R110" test="udt:DateTimeString &gt;= ../../../../ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:StartDateTime/udt:DateTimeString" flag="fatal">Start date of line period MUST be within invoice period.</assert>
    </rule>
    <rule context="rsm:SupplyChainTradeTransaction[ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:EndDateTime]/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:BillingSpecifiedPeriod/ram:EndDateTime">
      <assert id="PEPPOL-EN16931-R111" test="udt:DateTimeString &lt;= ../../../../ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:EndDateTime/udt:DateTimeString" flag="fatal">End date of line period MUST be within invoice period.</assert>
    </rule>
    <rule context="ram:IncludedSupplyChainTradeLineItem">
      <let name="lineExtensionAmount" value="
                    if (ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount) then
                        xs:decimal(ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount)
                    else
                        0"/>
      <let name="quantity" value="
                    if (ram:SpecifiedLineTradeDelivery/ram:BilledQuantity) then
                        xs:decimal(ram:SpecifiedLineTradeDelivery/ram:BilledQuantity)
                    else
                        1"/>
      <let name="priceAmount" value="
                    if (ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:ChargeAmount) then
                        xs:decimal(ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:ChargeAmount)
                    else
                        0"/>
      <let name="baseQuantity" value="
                    if (ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:BasisQuantity and xs:decimal(ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:BasisQuantity) != 0) then
                        xs:decimal(ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:BasisQuantity)
                    else
                        1"/>
      <let name="allowancesTotal" value="
                    if (ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeAllowanceCharge[normalize-space(ram:ChargeIndicator/udt:Indicator) = 'false']) then
                        round(sum(ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeAllowanceCharge[normalize-space(ram:ChargeIndicator/udt:Indicator) = 'false']/ram:ActualAmount/xs:decimal(.)) * 10 * 10) div 100
                    else
                        0"/>
      <let name="chargesTotal" value="
                    if (ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeAllowanceCharge[normalize-space(ram:ChargeIndicator/udt:Indicator) = 'true']) then
                        round(sum(ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeAllowanceCharge[normalize-space(ram:ChargeIndicator/udt:Indicator) = 'true']/ram:ActualAmount/xs:decimal(.)) * 10 * 10) div 100
                    else
                        0"/>
      <assert id="PEPPOL-EN16931-R120" test="u:slack($lineExtensionAmount, ($quantity * ($priceAmount div $baseQuantity)) + $chargesTotal - $allowancesTotal, 0.02)" flag="fatal">Invoice line net amount MUST equal (Invoiced quantity * (Item net price/item price base quantity) + Sum of invoice line charge amount - sum of invoice line allowance amount</assert>
      <assert id="PEPPOL-EN16931-R100" test="count(ram:SpecifiedLineTradeSettlement/ram:AdditionalReferencedDocument[ram:TypeCode='130']) &lt;=1" flag="fatal">Only one invoiced object is allowed pr line</assert>
      <assert id="PEPPOL-EN16931-R101" test="(not(ram:SpecifiedLineTradeSettlement/ram:AdditionalReferencedDocument) or (ram:SpecifiedLineTradeSettlement/ram:AdditionalReferencedDocument/ram:TypeCode='130'))" flag="fatal">Element Document reference can only be used for Invoice line object</assert>
    </rule>
    <rule context="ram:NetPriceProductTradePrice | ram:GrossPriceProductTradePrice">
      <assert id="PEPPOL-EN16931-R121" test="not(ram:BasisQuantity) or xs:decimal(ram:BasisQuantity) &gt; 0" flag="fatal">Base quantity MUST be a positive number above zero.</assert>
    </rule>
    <!-- PEPPOL-EN16931-R044 and PEPPOL-EN16931-R046 are not needed due to lack of elements for the EN. -->
    <!-- Price -->
    <rule context="ram:NetPriceProductTradePrice/ram:BasisQuantity[@unitCode] | ram:GrossPriceProductTradePrice/ram:BasisQuantity[@unitCode]">
      <assert id="PEPPOL-EN16931-R130" test="@unitCode = ../../../ram:SpecifiedLineTradeDelivery/ram:BilledQuantity/@unitCode" flag="fatal">Unit code of price base quantity MUST be same as invoiced quantity.</assert>
    </rule>
    <!-- Validation of ICD -->
    <rule context="ram:URIID[@schemeID = '0088'] | ram:ID[@schemeID = '0088'] | ram:GlobalID[@schemeID = '0088']">
      <assert id="PEPPOL-COMMON-R040" test="matches(normalize-space(), '^[0-9]+$') and u:gln(normalize-space())" flag="fatal">GLN must have a valid format according to GS1 rules.</assert>
    </rule>
    <rule context="ram:URIID[@schemeID = '0192'] | ram:ID[@schemeID = '0192'] | ram:GlobalID[@schemeID = '0192']">
      <assert id="PEPPOL-COMMON-R041" test="matches(normalize-space(), '^[0-9]{9}$') and u:mod11(normalize-space())" flag="fatal">Norwegian organization number MUST be stated in the correct format.</assert>
    </rule>
    <rule context="ram:URIID[@schemeID = '0184'] | ram:ID[@schemeID = '0184'] | ram:GlobalID[@schemeID = '0184']">
      <assert id="PEPPOL-COMMON-R042" test="(string-length(text()) = 10) and (substring(text(), 1, 2) = 'DK') and (string-length(translate(substring(text(), 3, 8), '1234567890', '')) = 0)" flag="fatal">Danish organization number (CVR) MUST be stated in the correct format.</assert>
    </rule>
    <rule context="ram:URIID[@schemeID = '0208'] | ram:ID[@schemeID = '0208'] | ram:GlobalID[@schemeID = '0208']">
      <assert id="PEPPOL-COMMON-R043" test="matches(normalize-space(), '^[0-9]{10}$') and u:mod97-0208(normalize-space())" flag="fatal">Belgian enterprise number MUST be stated in the correct format.</assert>
    </rule>
    <rule context="ram:URIID[@schemeID = '0201'] | ram:ID[@schemeID = '0201'] | ram:GlobalID[@schemeID = '0201']">
      <assert id="PEPPOL-COMMON-R044" test="u:checkCodiceIPA(normalize-space())" flag="warning">IPA Code (Codice Univoco Unità Organizzativa) must be stated in the correct format</assert>
    </rule>
    <rule context="ram:URIID[@schemeID = '0210'] | ram:ID[@schemeID = '0210'] | ram:GlobalID[@schemeID = '0210']">
      <assert id="PEPPOL-COMMON-R045" test="u:checkCF(normalize-space())" flag="warning">Tax Code (Codice Fiscale) must be stated in the correct format</assert>
    </rule>
    <rule context="ram:URIID[@schemeID = '9907']">
      <assert id="PEPPOL-COMMON-R046" test="u:checkCF(normalize-space())" flag="warning">Tax Code (Codice Fiscale) must be stated in the correct format</assert>
    </rule>
    <rule context="ram:URIID[@schemeID = '0211'] | ram:ID[@schemeID = '0211'] | ram:GlobalID[@schemeID = '0211']">
      <assert id="PEPPOL-COMMON-R047" test="u:checkPIVAseIT(normalize-space())" flag="warning">Italian VAT Code (Partita Iva) must be stated in the correct format</assert>
    </rule>
    <rule context="ram:URIID[@schemeID = '9906']">
      <assert id="PEPPOL-COMMON-R048" test="u:checkPIVAseIT(normalize-space())" flag="warning">Italian VAT Code (Partita Iva) must be stated in the correct format</assert>
    </rule>
    <rule context="ram:URIID[@schemeID = '0007'] | ram:ID[@schemeID = '0007'] | ram:GlobalID[@schemeID = '0007']">
      <assert id="PEPPOL-COMMON-R049" test="string-length(normalize-space()) = 10 and string(number(normalize-space())) != 'NaN' and u:checkSEOrgnr(normalize-space())" flag="fatal">Swedish organization number MUST be stated in the correct format.</assert>
    </rule>
	<rule context="ram:URIID[@schemeID = '0151'] | ram:ID[@schemeID = '0151'] | ram:GlobalID[@schemeID = '0151']">
      <assert id="PEPPOL-COMMON-R050" test="u:abn(normalize-space())" flag="fatal">Australian Business Number (ABN) MUST be stated in the correct format.</assert>
    </rule>

  </pattern>
  <!-- National rules -->
  <pattern>
    <!-- Norway -->
    <rule context="ram:SellerTradeParty[$supplierCountry = 'NO']">
      <assert id="NO-R-002" test="ram:SpecifiedTaxRegistration/ram:ID[@schemeID = 'FC'][normalize-space(text()) = 'Foretaksregisteret']" flag="warning">Most invoice issuers are required to append "Foretaksregisteret" to
                their invoice. "Dersom selger er aksjeselskap, allmennaksjeselskap eller filial av
                utenlandsk selskap skal også ordet «Foretaksregisteret» fremgå av salgsdokumentet,
                jf. foretaksregisterloven § 10-2."</assert>
      <assert id="NO-R-001" test="ram:SpecifiedTaxRegistration[ram:ID/@schemeID = 'VAT']/substring(ram:ID, 1, 2)='NO' and matches(ram:SpecifiedTaxRegistration[ram:ID/@schemeID = 'VAT']/substring(ram:ID,3) , '^[0-9]{9}MVA$')
                and u:mod11(substring(ram:SpecifiedTaxRegistration[ram:ID/@schemeID = 'VAT']/ram:ID, 3, 9)) or not(ram:SpecifiedTaxRegistration[ram:ID/@schemeID = 'VAT']/substring(ram:ID, 1, 2)='NO')" flag="fatal">For Norwegian suppliers, a VAT number MUST be the country code prefix NO followed by a valid Norwegian organization number (nine numbers) followed by the letters MVA.</assert>
    </rule>
  </pattern>
  <!-- DENMARK -->
  <pattern>
    <let name="DKSupplierCountry" value="rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:PostalTradeAddress/ram:CountryID"/>
    <let name="DKCustomerCountry" value="rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:PostalTradeAddress/ram:CountryID"/>
    <!-- Document level -->
    <rule context="rsm:CrossIndustryInvoice[$DKSupplierCountry = 'DK']">
      <!--Check for Legal entity-->
      <assert id="DK-R-002" test="(normalize-space(rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:SpecifiedLegalOrganization/ram:ID/text()) != '')" flag="fatal">Danish suppliers MUST provide legal entity.</assert>
      <!--Check for Non VAT Tax code-->
      <assert id="DK-R-004" test="not((($DKCustomerCountry = 'DK') and (rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeAllowanceCharge/ram:ReasonCode = 'ZZZ'))
                              and not ((string-length(normalize-space(rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeAllowanceCharge/ram:Reason/text())) = 4
                                       and number(rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeAllowanceCharge/ram:Reason) &gt;= 0)
                                       and number(rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeAllowanceCharge/ram:Reason &lt;= 9999)
                                      )
                              )" flag="fatal">When specifying non-VAT Taxes for Danish customers, Danish suppliers MUST use the SpecifiedTradeAllowanceCharge/ReasonCode="ZZZ" and the 4-digit Tax category MUST be specified as Reason</assert>
      <assert id="DK-R-013" test="not(($DKCustomerCountry = 'DK') and
                                  ( ((boolean(rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:GlobalID))
                                     and (normalize-space(rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:GlobalID/@schemeID) = ''))
                                   or
                                    ((boolean(rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:GlobalID))
                                     and (normalize-space(rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:GlobalID/@schemeID) = ''))
                                  )
                              )" flag="fatal">For Danish Suppliers it is mandatory to use schemeID when GlobalID is used for SellerTradeParty or BuyerTradeParty</assert>
      <assert id="DK-R-014" test="not((boolean(rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:SpecifiedLegalOrganization/ram:ID))
                                     and (normalize-space(rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:SpecifiedLegalOrganization/ram:ID/@schemeID) != '0184')
                              )" flag="fatal">For Danish Suppliers it is mandatory to specify schemeID as "0184" when SpecifiedLegalOrganization is used for SellerTradeParty</assert>
      <assert id="DK-R-016" test="not((($DKCustomerCountry = 'DK') and (normalize-space(rsm:ExchangedDocument/ram:TypeCode/text()) = '381'))
                              and (number(rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:DuePayableAmount/text()) &lt; 0)
                              )" flag="fatal">For Danish Suppliers, a Credit note cannot have a negative total (DuePayableAmount)</assert>
    </rule>
    <rule context="rsm:CrossIndustryInvoice[$DKSupplierCountry = 'DK' and $DKCustomerCountry = 'DK']/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans">
      <assert id="DK-R-005" test="(contains(' 1 10 31 42 48 49 50 58 59 93 97 ', concat(' ', ram:TypeCode, ' ')))" flag="fatal">For Danish suppliers the following Payment means type codes are allowed: 1, 10, 31, 42, 48, 49, 50, 58, 59, 93 and 97</assert>
      <assert id="DK-R-006" test="not( ((ram:TypeCode = '31') or (ram:TypeCode = '42'))
                              and not((normalize-space(ram:PayeePartyCreditorFinancialAccount/ram:IBANID/text()) != '') and (normalize-space(ram:PayeeSpecifiedCreditorFinancialInstitution/ram:BICID/text()) != ''))
                              )" flag="fatal">For Danish suppliers, bank account and registration account are mandatory if payment means is 31 or 42</assert>
      <assert id="DK-R-007" test="not((ram:TypeCode = '49')
                              and not((normalize-space(../ram:CreditorReferenceID/text()) != '')
                                      and (normalize-space(ram:SpecifiedTradePaymentTerms/ram:DirectDebitMandateID/text()) != ''))
                              )" flag="fatal">For Danish suppliers DirectDebitMandateID and CreditorReferenceID are mandatory when payment means is 49</assert>
      <assert id="DK-R-008" test="not((ram:TypeCode = '50')
                              and not(((substring(../ram:PaymentReference, 0, 4) = '01#')
                                        or (substring(../ram:PaymentReference, 0, 4) = '04#')
                                        or (substring(../ram:PaymentReference, 0, 4) = '15#'))
                                      and (string-length(ram:PayeePartyCreditorFinancialAccount/ram:IBANID/text()) = 7)
                                      )
                              )" flag="fatal">For Danish Suppliers PaymentReference is mandatory and MUST start with 01#, 04# or 15# (kortartkode), and PayeePartyCreditorFinancialAccount/IBANID (Giro kontonummer) is mandatory and must be 7 characters long, when payment means equals 50 (Giro)</assert>
      <assert id="DK-R-009" test="not((ram:TypeCode = '50')
                              and ((substring(../ram:PaymentReference, 0, 4) = '04#')
                                    or (substring(../ram:PaymentReference, 0, 4)  = '15#'))
                              and not(string-length(../ram:PaymentReference) = 19)
                              )" flag="fatal">For Danish Suppliers if the PaymentReference is prefixed with 04# or 015# the 16 digits instruction Id must be added to the PaymentReference eg. "04#1234567890123456" when Payment means equals 50 (Giro)</assert>
      <assert id="DK-R-010" test="not((ram:TypeCode = '93')
                              and not(((substring(../ram:PaymentReference, 0, 4) = '71#')
                                        or (substring(../ram:PaymentReference, 0, 4) = '73#')
                                        or (substring(../ram:PaymentReference, 0, 4) = '75#'))
                                      and (string-length(ram:PayeePartyCreditorFinancialAccount/ram:IBANID/text()) = 8)
                                      )
                              )" flag="fatal">For Danish Suppliers the PaymentReference is mandatory and MUST start with 71#, 73# or 75# (kortartkode) and and PayeePartyCreditorFinancialAccount/IBANID  (Kreditornummer) is mandatory and must be exactly 8 characters long, when Payment means equals 93 (FIK)</assert>
      <assert id="DK-R-011" test="not((ram:TypeCode = '93')
                              and ((substring(../ram:PaymentReference, 0, 4) = '71#')
                                    or (substring(../ram:PaymentReference, 0, 4)  = '75#'))
                              and not((string-length(../ram:PaymentReference) = 18)
                                    or (string-length(../ram:PaymentReference) = 19))
                              )" flag="fatal">For Danish Suppliers if the PaymentReference is prefixed with 71# or 75# the 15-16 digits instruction Id must be added to the PaymentReference eg. "71#1234567890123456" when payment Method equals 93 (FIK)</assert>
    </rule>
    <rule context="rsm:CrossIndustryInvoice[$DKSupplierCountry = 'DK' and $DKCustomerCountry = 'DK']/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem">
      <!-- Chedk for commodityCode on linelevel -->
      <assert id="DK-R-003" test="not((ram:SpecifiedTradeProduct/ram:DesignatedProductClassification/ram:ClassCode/@listID = 'TST')
                              and not((ram:SpecifiedTradeProduct/ram:DesignatedProductClassification/ram:ClassCode/@listVersionID = '19.05.01')
                                     or (ram:SpecifiedTradeProduct/ram:DesignatedProductClassification/ram:ClassCode/@listVersionID = '19.0501')
                               )
                              )" flag="warning">If ItemClassification is provided from Danish suppliers, UNSPSC version 19.0501 should be used</assert>
    </rule>
  </pattern>
  <!-- Italian rules -->
  <pattern>
    <rule context="ram:SellerTradeParty[$supplierCountry = 'IT']/ram:SpecifiedTaxRegistration">
      <assert id="IT-R-001" test=" ram:ID[normalize-space(@schemeID) !='FC'] or matches(normalize-space(ram:ID[normalize-space(@schemeID) ='FC']) ,'^[A-Z0-9]{11,16}$') " flag="fatal"> [IT-R-001] BT-32 (Seller tax registration identifier) - For Italian suppliers BT-32 minimum length 11 and maximum length shall be 16.  Per i fornitori italiani il BT-32 deve avere una lunghezza tra 11 e 16 caratteri</assert>
    </rule>
    <rule context="ram:SellerTradeParty[$supplierCountry = 'IT']">
      <assert id="IT-R-002" test="(ram:PostalTradeAddress/ram:LineOne)" flag="fatal"> [IT-R-002] BT-35 (Seller address line 1) - Italian suppliers MUST provide the postal address line 1 - I fornitori italiani devono indicare l'indirizzo postale.</assert>
      <assert id="IT-R-003" test="(ram:PostalTradeAddress/ram:CityName)" flag="fatal"> [IT-R-003] BT-37 (Seller city) - Italian suppliers MUST provide the postal address city - I fornitori italiani devono indicare la città di residenza.</assert>
      <assert id="IT-R-004" test="(ram:PostalTradeAddress/ram:PostcodeCode)" flag="fatal"> [IT-R-004] BT-38 (Seller post code) - Italian suppliers MUST provide the postal address post code - I fornitori italiani devono indicare il CAP di residenza.</assert>
    </rule>
  </pattern>
  <!-- Swedish rules -->
  <pattern>
    <rule context="rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty[ram:PostalTradeAddress/ram:CountryID = 'SE' and ram:SpecifiedTaxRegistration/substring(ram:ID[@schemeID = 'VAT'], 1, 2) = 'SE']">
      <assert id="SE-R-001" test="string-length(normalize-space(ram:SpecifiedTaxRegistration/ram:ID[@schemeID = 'VAT'])) = 14" flag="fatal">For Swedish suppliers, Swedish VAT-numbers must consist of 14 characters.</assert>
      <assert id="SE-R-002" test="string(number(substring(ram:SpecifiedTaxRegistration/ram:ID[@schemeID = 'VAT'], 3, 12))) != 'NaN'" flag="fatal">For Swedish suppliers, the Swedish VAT-numbers must have the trailing 12 characters in numeric form</assert>
    </rule>
    <rule context="rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:SpecifiedLegalOrganization[../ram:PostalTradeAddress/ram:CountryID = 'SE' and ram:ID]">
      <assert id="SE-R-003" test="string(number(ram:ID)) != 'NaN'" flag="warning">Swedish organisation numbers should be numeric.</assert>
      <assert id="SE-R-004" test="string-length(normalize-space(ram:ID)) = 10" flag="warning">Swedish organisation numbers consist of 10 characters.</assert>
	  <assert id="SE-R-013" test="u:checkSEOrgnr(normalize-space(ram:ID))" flag="warning">The last digit of a Swedish organization number must be valid according to the Luhn algorithm.</assert>	  
    </rule>
    <rule context="rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty[ram:PostalTradeAddress/ram:CountryID = 'SE' and ram:SpecifiedLegalOrganization/ram:ID]/ram:SpecifiedTaxRegistration/ram:ID[@schemeID = 'FC']">
      <assert id="SE-R-005" test="normalize-space(upper-case(.)) = 'GODKÄND FÖR F-SKATT'" flag="fatal">For Swedish suppliers, when using Seller tax registration identifier, 'Godkänd för F-skatt' must be stated</assert>
    </rule>
    <rule context="//ram:ApplicableTradeTax[/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty[ram:PostalTradeAddress/ram:CountryID = 'SE']/ram:SpecifiedTaxRegistration[substring(ram:ID[@schemeID = 'VAT'], 1, 2) = 'SE']] | //ram:CategoryTradeTax[/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty[ram:PostalTradeAddress/ram:CountryID = 'SE']/ram:SpecifiedTaxRegistration[substring(ram:ID[@schemeID = 'VAT'], 1, 2) = 'SE']]">
      <assert id="SE-R-006" test="number(ram:RateApplicablePercent) = 25 or number(ram:RateApplicablePercent) = 12 or number(ram:RateApplicablePercent) = 6" flag="fatal">For Swedish suppliers, only standard VAT rate of 6, 12 or 25 are used</assert>
    </rule>
    <rule context="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction[ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty[ram:PostalTradeAddress/ram:CountryID = 'SE']]/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans[normalize-space(ram:TypeCode) = '30' and normalize-space(ram:PayeeSpecifiedCreditorFinancialInstitution/ram:BICID) = 'SE:PLUSGIRO']/ram:PayeePartyCreditorFinancialAccount/ram:ProprietaryID">
      <assert id="SE-R-007" test="string(number(normalize-space(.))) != 'NaN'" flag="warning">For Swedish suppliers using Plusgiro, the Account ID must be numeric </assert>
      <assert id="SE-R-010" test="string-length(normalize-space(.)) &gt;= 2 and string-length(normalize-space(.)) &lt;= 8" flag="warning">For Swedish suppliers using Plusgiro, the Account ID must have 2-8 characters</assert>
    </rule>
    <rule context="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction[ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty[ram:PostalTradeAddress/ram:CountryID = 'SE']]/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans[normalize-space(ram:TypeCode) = '30' and normalize-space(ram:PayeeSpecifiedCreditorFinancialInstitution/ram:BICID) = 'SE:BANKGIRO']/ram:PayeePartyCreditorFinancialAccount/ram:ProprietaryID">
      <assert id="SE-R-008" test="string(number(normalize-space(.))) != 'NaN'" flag="warning">For Swedish suppliers using Bankgiro, the Account ID must be numeric </assert>
      <assert id="SE-R-009" test="string-length(normalize-space(.)) = 7 or string-length(normalize-space(.)) = 8" flag="warning">For Swedish suppliers using Bankgiro, the Account ID must have 7-8 characters</assert>
    </rule>
    <rule context="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction[ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty[ram:PostalTradeAddress/ram:CountryID = 'SE']]/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans[normalize-space(ram:TypeCode) = '50' or normalize-space(ram:TypeCode) = '56']">
      <assert id="SE-R-011" test="false()" flag="warning">For Swedish suppliers using Swedish Bankgiro or Plusgiro, the proper way to indicate this is to use Code 30 for PaymentMeans and FinancialInstitutionBranch ID with code SE:BANKGIRO or SE:PLUSGIRO</assert>
    </rule>
    <rule context="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction[ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty[ram:PostalTradeAddress/ram:CountryID = 'SE'] and ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty[ram:PostalTradeAddress/ram:CountryID = 'SE']]/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans[normalize-space(ram:TypeCode) = '31']">
      <assert id="SE-R-012" test="false()" flag="warning">For domestic transactions between Swedish trading partners, credit transfer should be indicated by PaymentMeansCode=”30”</assert>
    </rule>
  </pattern>
  <!-- NETHERLANDS -->
  <pattern>
  <let name="supplierCountryIsNL" value="(upper-case(normalize-space(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:PostalTradeAddress/ram:CountryID)) = 'NL')" />
  <let name="customerCountryIsNL" value="(upper-case(normalize-space(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:PostalTradeAddress/ram:CountryID)) = 'NL')" />
  <let name="taxRepresentativeCountryIsNL" value="(upper-case(normalize-space(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTaxRepresentativeTradeParty/ram:PostalTradeAddress/ram:CountryID)) = 'NL')" />

  <rule context="/rsm:CrossIndustryInvoice/rsm:ExchangedDocument[some $code in tokenize('81 83 381 396 532', '\s') satisfies normalize-space(ram:TypeCode) = $code][$supplierCountryIsNL]">
    <!-- Original rule in NLCIUS: BR-NL-9 -->
    <assert id="NL-R-001" test="//ram:ApplicableHeaderTradeSettlement/ram:InvoiceReferencedDocument/ram:IssuerAssignedID" flag="fatal">[NL-R-001] For suppliers in the Netherlands, if the document is a creditnote, the document MUST contain an invoice reference (ram:ApplicableHeaderTradeSettlement/ram:InvoiceReferencedDocument/ram:IssuerAssignedID)</assert>
  </rule>

    <rule context="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:PostalTradeAddress[$supplierCountryIsNL]">
      <!-- Original rule in NLCIUS: BR-NL-3 -->
      <assert id="NL-R-002" test="ram:LineOne and ram:CityName and ram:PostcodeCode" flag="fatal">[NL-R-002] For suppliers in the Netherlands the supplier's address (ram:SellerTradeParty/ram:PostalTradeAddress) MUST contain street name (ram:LineOne), city (ram:CityName) and post code (ram:PostcodeCode)</assert>
    </rule>

    <rule context="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:SpecifiedLegalOrganization/ram:ID[$supplierCountryIsNL]">
      <!-- Original rule in NLCIUS: BR-NL-1 -->
      <assert id="NL-R-003" test="(contains(concat(' ', string-join(@schemeID, ' '), ' '), ' 0106 ') or contains(concat(' ', string-join(@schemeID, ' '), ' '), ' 0190 ')) and (normalize-space(.) != '')" flag="fatal">[NL-R-003] For suppliers in the Netherlands, the legal entity identifier MUST be either a KVK or OIN number (schemeID 0106 or 0190)</assert>
    </rule>

    <rule context="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:PostalTradeAddress[$supplierCountryIsNL and $customerCountryIsNL]">
      <!-- Original rule in NLCIUS: BR-NL-4 -->
      <assert id="NL-R-004" test="ram:LineOne and ram:CityName and ram:PostcodeCode" flag="fatal">[NL-R-004] For suppliers in the Netherlands, if the customer is in the Netherlands, the customer address (ram:BuyerTradeParty/ram:PostalTradeAddress) MUST contain street name (ram:LineOne), city (ram:CityName) and post code (ram:PostcodeCode)</assert>
    </rule>

    <rule context="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:SpecifiedLegalOrganization/ram:ID[$supplierCountryIsNL and $customerCountryIsNL]">
      <!-- Original rule in NLCIUS: BR-NL-10 -->
      <assert id="NL-R-005" test="
          (contains(concat(' ', string-join(@schemeID, ' '), ' '), ' 0106 ')
           or
           contains(concat(' ', string-join(@schemeID, ' '), ' '), ' 0190 ')
          ) and (normalize-space(.) != '')
      " flag="fatal">[NL-R-005] For suppliers in the Netherlands, if the customer is in the Netherlands, the customer's legal entity identifier MUST be either a KVK or OIN number (schemeID 0106 or 0190)</assert>
    </rule>

    <rule context="rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTaxRepresentativeTradeParty/ram:PostalTradeAddress[$supplierCountryIsNL and $taxRepresentativeCountryIsNL]">
      <!-- Original rule in NLCIUS: BR-NL-5 -->
      <assert id="NL-R-006" test="ram:LineOne and ram:CityName and ram:PostcodeCode" flag="fatal">[NL-R-006] For suppliers in the Netherlands, if the fiscal representative is in the Netherlands, the representative's address (ram:SellerTaxRepresentativeTradeParty/ram:PostalTradeAddress) MUST contain street name (ram:LineOne), city (ram:CityName) and post code (ram:PostcodeCode)</assert>
    </rule>

    <rule context="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation[$supplierCountryIsNL]">
      <!-- Original rule in NLCIUS: BR-NL-11 -->
      <assert id="NL-R-007" test="(normalize-space(/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:TypeCode/text()) != '381' and
                                   xs:decimal(ram:DuePayableAmount) &lt;= 0.0) or
                                  (normalize-space(/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:TypeCode/text()) = '381' and
                                   xs:decimal(ram:DuePayableAmount) &gt;= 0.0) or
                                  (/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans)" flag="fatal">[NL-R-007] For suppliers in the Netherlands, the supplier MUST provide a means of payment (ram:SpecifiedTradeSettlementPaymentMeans) if the payment is from customer to supplier</assert>
    </rule>

    <rule context="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans[$supplierCountryIsNL]">
      <!-- Original rule in NLCIUS: BR-NL-12 -->
      <assert id="NL-R-008" test="normalize-space(ram:TypeCode) = '30' or
                normalize-space(ram:TypeCode) = '48' or
                normalize-space(ram:TypeCode) = '49' or
                normalize-space(ram:TypeCode) = '57' or
                normalize-space(ram:TypeCode) = '58' or
                normalize-space(ram:TypeCode) = '59'" flag="fatal">[NL-R-008] For suppliers in the Netherlands, the payment means code (ram:SpecifiedTradeSettlementPaymentMeans/ram:TypeCode) MUST be one of 30, 48, 49, 57, 58 or 59</assert>
    </rule>

    <rule context="rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeAgreement/ram:BuyerOrderReferencedDocument/ram:LineID[$supplierCountryIsNL]">
      <!-- Original rule in NLCIUS: BR-NL-13 -->
      <assert id="NL-R-009" test="exists(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerOrderReferencedDocument/ram:IssuerAssignedID)" flag="fatal">[NL-R-009] For suppliers in the Netherlands, if an order line reference (ram:BuyerOrderReferencedDocument/ram:LineID) is used, there must be an order reference on the document level (rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerOrderReferencedDocument/ram:IssuerAssignedID)</assert>
    </rule>
  </pattern>
  <!-- Restricted code lists and formatting -->
  <pattern>
    <let name="ISO3166" value="tokenize('AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW', '\s')"/>
    <let name="ISO4217" value="tokenize('AFN EUR ALL DZD USD AOA XCD XCD ARS AMD AWG AUD AZN BSD BHD BDT BBD BYN BZD XOF BMD INR BTN BOB BOV USD BAM BWP NOK BRL USD BND BGN XOF BIF CVE KHR XAF CAD KYD XAF XAF CLP CLF CNY AUD AUD COP COU KMF CDF XAF NZD CRC XOF HRK CUP CUC ANG CZK DKK DJF XCD DOP USD EGP SVC USD XAF ERN ETB FKP DKK FJD XPF XAF GMD GEL GHS GIP DKK XCD USD GTQ GBP GNF XOF GYD HTG USD AUD HNL HKD HUF ISK INR IDR XDR IRR IQD GBP ILS JMD JPY GBP JOD KZT KES AUD KPW KRW KWD KGS LAK LBP LSL ZAR LRD LYD CHF MOP MKD MGA MWK MYR MVR XOF USD MRO MUR XUA MXN MXV USD MDL MNT XCD MAD MZN MMK NAD ZAR AUD NPR XPF NZD NIO XOF NGN NZD AUD USD NOK OMR PKR USD PAB USD PGK PYG PEN PHP NZD PLN USD QAR RON RUB RWF SHP XCD XCD XCD WST STD SAR XOF RSD SCR SLL SGD ANG XSU SBD SOS ZAR SSP LKR SDG SRD NOK SZL SEK CHF CHE CHW SYP TWD TJS TZS THB USD XOF NZD TOP TTD TND TRY TMT USD AUD UGX UAH AED GBP USD USD USN UYU UYI UZS VUV VEF VND USD USD XPF MAD YER ZMW ZWL XBA XBB XBC XBD XTS XXX XAU XPD XPT XAG', '\s')"/>
    <let name="MIMECODE" value="tokenize('application/pdf image/png image/jpeg text/csv application/vnd.openxmlformats-officedocument.spreadsheetml.sheet application/vnd.oasis.opendocument.spreadsheet', '\s')"/>
    <let name="UNCL2005" value="tokenize('3 35 432', '\s')"/>
    <let name="UNCL5189" value="tokenize('41 42 60 62 63 64 65 66 67 68 70 71 88 95 100 102 103 104 105', '\s')"/>
    <let name="UNCL7161" value="tokenize('AA AAA AAC AAD AAE AAF AAH AAI AAS AAT AAV AAY AAZ ABA ABB ABC ABD ABF ABK ABL ABN ABR ABS ABT ABU ACF ACG ACH ACI ACJ ACK ACL ACM ACS ADC ADE ADJ ADK ADL ADM ADN ADO ADP ADQ ADR ADT ADW ADY ADZ AEA AEB AEC AED AEF AEH AEI AEJ AEK AEL AEM AEN AEO AEP AES AET AEU AEV AEW AEX AEY AEZ AJ AU CA CAB CAD CAE CAF CAI CAJ CAK CAL CAM CAN CAO CAP CAQ CAR CAS CAT CAU CAV CAW CD CG CS CT DAB DAD DL EG EP ER FAA FAB FAC FC FH FI GAA HAA HD HH IAA IAB ID IF IR IS KO L1 LA LAA LAB LF MAE MI ML NAA OA PA PAA PC PL RAB RAC RAD RAF RE RF RH RV SA SAA SAD SAE SAI SG SH SM SU TAB TAC TT TV V1 V2 WH XAA YY ZZZ', '\s')"/>
    <let name="UNCL5305" value="tokenize('AE E S Z G O K L M', '\s')"/>
    <let name="eaid" value="tokenize('0002 0007 0009 0037 0060 0088 0096 0097 0106 0130 0135 0142 0147 0151 0170 0183 0184 0188 0190 0191 0192 0193 0195 0196 0198 0199 0200 0201 0202 0204 0208 0209 0210 0211 0212 0213 0215 0216 0218 0221 0230 9901 9906 9907 9910 9913 9914 9915 9918 9919 9920 9922 9923 9924 9925 9926 9927 9928 9929 9930 9931 9932 9933 9934 9935 9936 9937 9938 9939 9940 9941 9942 9943 9944 9945 9946 9947 9948 9949 9950 9951 9952 9953 9957', '\s')"/>
    <rule context="ram:AttachmentBinaryObject[@mimeCode]">
      <assert id="PEPPOL-EN16931-CL001" test="
                    some $code in $MIMECODE
                        satisfies @mimeCode = $code" flag="fatal">Mime code must be according to subset of IANA code list.</assert>
    </rule>
    <rule context="ram:SpecifiedTradeAllowanceCharge[normalize-space(ram:ChargeIndicator/udt:Indicator) = 'false']/ram:ReasonCode">
      <assert id="PEPPOL-EN16931-CL002" test="
                    some $code in $UNCL5189
                        satisfies normalize-space(text()) = $code" flag="fatal">Reason code MUST be according to subset of UNCL 5189 D.16B.</assert>
    </rule>
    <rule context="ram:SpecifiedTradeAllowanceCharge[normalize-space(ram:ChargeIndicator/udt:Indicator) = 'true']/ram:ReasonCode">
      <assert id="PEPPOL-EN16931-CL003" test="
                    some $code in $UNCL7161
                        satisfies normalize-space(text()) = $code" flag="fatal">Reason code MUST be according to UNCL 7161 D.16B.</assert>
    </rule>
    <!-- PEPPOL-EN16931-CL006 is omitted due to lack of description code for invoice period in CII syntax. -->
    <rule context="ram:TaxTotalAmount[@currencyID]">
      <assert id="PEPPOL-EN16931-CL007" test="
                    some $code in $ISO4217
                        satisfies @currencyID = $code" flag="fatal">Currency code must be according to ISO 4217:2005</assert>
    </rule>
    <rule context="ram:ExchangedDocument/ram:TypeCode">
      <assert id="PEPPOL-EN16931-P0100" test="
                    $profile != '01' or (some $code in tokenize('71 102 218 219 331 382 553 817 870 875 876 877 380 383 386 388 393 82 80 84 395 575 623 780 381 396 81 83 532', '\s')
                        satisfies normalize-space(text()) = $code)" flag="fatal">Invoice type code MUST be set according to the profile.</assert>
    </rule>
    <!-- PEPPOL-EN16931-P0101 is part of PEPPOL-EN16931-P0100. -->
    <rule context="udt:DateTimeString">
      <assert id="PEPPOL-EN16931-F001" test="normalize-space(@format) = '102' and string-length(text()) = 8 and matches(normalize-space(text()), '20[0-9]{6}')" flag="fatal">A date MUST be formatted YYYYMMDD.</assert>
    </rule>
    <rule context="ram:BuyerTradeParty/ram:URIUniversalCommunication/ram:URIID | ram:SellerTradeParty/ram:URIUniversalCommunication/ram:URIID">
      <assert id="PEPPOL-EN16931-CL008" test="
                some $code in $eaid
                satisfies @schemeID = $code" flag="fatal">Electronic address identifier scheme must be from the codelist "Electronic Address Identifier Scheme"</assert>
    </rule>
  </pattern>
</schema>
