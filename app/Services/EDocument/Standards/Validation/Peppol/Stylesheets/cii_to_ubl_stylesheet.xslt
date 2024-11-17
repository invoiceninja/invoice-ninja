<?xml version="1.0" encoding="UTF-8"?>
<!-- 
******************************************************************************************************************
		Title= Tranformation stylesheet from UN/CEFACT Cross Industry Invoice D16B to OASIS UBL 2.1 Invoice and CreditNote	
		Publisher= Single Face To Industry, Tekniska Kansli, www.sfti.se
		Creator= Single Face To Industry/Martin Forsberg, martin.forsberg@ecru.se
		Created= 2019-11-30
		Description=This stylesheet transforms valid Cross Industry Invoice D16B instances, which conformes to:
				CEN/EN 16931-1:2017, Electronic invoicing - Part 1: Semantic data model of the core elements of an electronic invoice,
				CEN/TS 16931-3-3:2017, Electronic invoicing - Part 3 - 3: Syntax binding for UN/CEFACT XML Cross Industry Invoice D16B and
				Core Invoice Usage Specification - PEPPOL BIS Billing 3.0
				into UBL 2.1 Invoice and CreditNote
		License: Licensed under European Union Public License (EUPL) version 1.2.
*******************************************************************************************************************
-->
<xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:fn="http://www.w3.org/2005/xpath-functions" xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100" xmlns:qdt="urn:un:unece:uncefact:data:standard:QualifiedDataType:100" xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100" xmlns:udt="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:inv="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:crd="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2" xmlns:sfti="urn:sfti:se:xslt:functions" exclude-result-prefixes="xs fn rsm ram udt qdt sfti crd inv">
	<xsl:output method="xml" encoding="UTF-8" byte-order-mark="no" indent="yes"/>
	<!-- 
Functions and variables -->
	<xsl:variable name="DocumentCurrencyID" select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:InvoiceCurrencyCode"/>
	<xsl:variable name="TaxCurrencyID" select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:TaxCurrencyCode"/>
	<!-- 
Version number of this stylesheet. For tracking purposes -->
	<xsl:variable name="StylesheetVersionID">1.0</xsl:variable>
	<!-- 
the original CII-xml can be included in the UBLExtension element for tracability and archiving purposes. 
This functionality is recommended for receivers of invoices but must not be used by issuers. 
Set the variable IncludeOriginalXML to true if the CII should be included.
N.B. The use of UBLExtension will result in warnings when validating the UBL Invoice/CreditNote.-->
	<xsl:variable name="IncludeOriginalXML">false</xsl:variable>
	<xsl:function name="sfti:CIIDateToXSDDate" as="xs:date">
		<xsl:param name="CIIDate" as="xs:string"/>
		<xsl:sequence select="xs:date(fn:concat(fn:concat(fn:concat(fn:concat(fn:substring($CIIDate, 1, 4), '-'), fn:substring($CIIDate, 5, 2)), '-'), fn:substring($CIIDate, 7, 2)))"/>
	</xsl:function>
	<xsl:template match="/">
		<xsl:if test="not(exists(/rsm:CrossIndustryInvoice/rsm:ExchangedDocument[contains('80 82 84 380 383 386 393 395 575 623 780 81 83 381 396 532', normalize-space(ram:TypeCode))]))">
			'Not a valid Invoice Type Code according to PEPPOL BIS Billing 3'
	</xsl:if>
		<xsl:variable name="SourceIsInvoice" select="exists(/rsm:CrossIndustryInvoice/rsm:ExchangedDocument[contains('80 82 84 380 383 386 393 395 575 623 780', normalize-space(ram:TypeCode))])"/>
		<!-- Root element dependent on type of invoice (as CII only use code, UBL different document models) -->
		<xsl:comment>
		****************************************************************************************************************************************************************************************************
		This XML Instance has been created by converting a Cross Indistry Invoice D16B to the UBL format. The transformation stylesheet version was: <xsl:value-of select="$StylesheetVersionID"/> and the transformation took place: <xsl:value-of select="current-dateTime()"/>
		****************************************************************************************************************************************************************************************************
		</xsl:comment>
		<xsl:element name="{if ( $SourceIsInvoice ) then 'inv:Invoice' else 'crd:CreditNote'}">
			<xsl:namespace name="cac">urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2</xsl:namespace>
			<xsl:namespace name="cbc">urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2</xsl:namespace>
			<xsl:if test="$IncludeOriginalXML='true'">
				<cec:UBLExtensions xmlns:cec="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">
					<cec:UBLExtension>
						<cec:ExtensionContent>
							<xsl:comment>
						***************************************************************************************************************************************************************************	
						The following XML, inside this Extension element, is the original CrossIndustryInvoice which has been transformed into UBL. It is contained here for tracability purposes.
						***************************************************************************************************************************************************************************	
						</xsl:comment>
							<xsl:apply-templates select="/" mode="OutputXML"/>
						</cec:ExtensionContent>
					</cec:UBLExtension>
				</cec:UBLExtensions>
			</xsl:if>
			<!-- Specification Identifier -->
			<cbc:CustomizationID>urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0</cbc:CustomizationID>
			<!-- Process Identifier -->
			<cbc:ProfileID>urn:fdc:peppol.eu:2017:poacc:billing:01:1.0</cbc:ProfileID>
			<!-- Invoice Identifier -->
			<cbc:ID>
				<xsl:value-of select="rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:ID"/>
			</cbc:ID>
			<!-- Invoice Issue date-->
			<cbc:IssueDate>
				<xsl:value-of select="sfti:CIIDateToXSDDate(rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString/text())"/>
			</cbc:IssueDate>
			<!-- Invoice due date-->
			<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:DueDateDateTime/udt:DateTimeString">
				<xsl:if test="$SourceIsInvoice">
					<cbc:DueDate>
						<xsl:value-of select="sfti:CIIDateToXSDDate(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:DueDateDateTime/udt:DateTimeString/text())"/>
					</cbc:DueDate>
				</xsl:if>
			</xsl:if>
			<!-- Invoice type code -->
			<xsl:if test="$SourceIsInvoice">
				<cbc:InvoiceTypeCode>
					<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:TypeCode"/>
				</cbc:InvoiceTypeCode>
			</xsl:if>
			<!-- Credit note type code -->
			<xsl:if test="not($SourceIsInvoice)">
				<!-- Invoice tax point date in credit note, located here-->
				<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax/ram:TaxPointDate/udt:DateString">
					<cbc:TaxPointDate>
						<xsl:value-of select="sfti:CIIDateToXSDDate(rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax[exists(ram:TaxPointDate)][1]/ram:TaxPointDate/udt:DateString/text())"/>
					</cbc:TaxPointDate>
				</xsl:if>
				<cbc:CreditNoteTypeCode>
					<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:TypeCode"/>
				</cbc:CreditNoteTypeCode>
			</xsl:if>
			<!-- Invoice Note-->
			<xsl:if test="/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:IncludedNote/ram:Content">
				<cbc:Note>
					<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:IncludedNote/ram:Content"/>
				</cbc:Note>
			</xsl:if>
			<!-- if invoice, then taxpoint date located here -->
			<xsl:if test="$SourceIsInvoice">
				<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax/ram:TaxPointDate/udt:DateString">
					<cbc:TaxPointDate>
						<xsl:value-of select="sfti:CIIDateToXSDDate(rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax[exists(ram:TaxPointDate)][1]/ram:TaxPointDate/udt:DateString/text())"/>
					</cbc:TaxPointDate>
				</xsl:if>
			</xsl:if>
			<!-- Invoice currency code-->
			<cbc:DocumentCurrencyCode>
				<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:InvoiceCurrencyCode"/>
			</cbc:DocumentCurrencyCode>
			<!-- Invoice tax currency code-->
			<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:TaxCurrencyCode">
				<cbc:TaxCurrencyCode>
					<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:TaxCurrencyCode"/>
				</cbc:TaxCurrencyCode>
			</xsl:if>
			<!-- accounting code -->
			<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:ReceivableSpecifiedTradeAccountingAccount/ram:ID">
				<cbc:AccountingCost>
					<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:ReceivableSpecifiedTradeAccountingAccount/ram:ID"/>
				</cbc:AccountingCost>
			</xsl:if>
			<!-- Buyer reference-->
			<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerReference">
				<cbc:BuyerReference>
					<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerReference"/>
				</cbc:BuyerReference>
			</xsl:if>
			<!-- Invoice period -->
			<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod or /rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax[1]/ram:DueDateTypeCode">
				<cac:InvoicePeriod>
					<!-- Invoice period start-->
					<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:StartDateTime/udt:DateTimeString">
						<cbc:StartDate>
							<xsl:value-of select="sfti:CIIDateToXSDDate(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:StartDateTime/udt:DateTimeString/text())"/>
						</cbc:StartDate>
					</xsl:if>
					<!-- Invoice period end-->
					<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:EndDateTime/udt:DateTimeString">
						<cbc:EndDate>
							<xsl:value-of select="sfti:CIIDateToXSDDate(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:EndDateTime/udt:DateTimeString/text())"/>
						</cbc:EndDate>
					</xsl:if>
					<!-- Value added tax point date code -->
					<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax[1]/ram:DueDateTypeCode">
						<cbc:DescriptionCode>
							<xsl:choose>
								<xsl:when test="fn:normalize-space(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax[1]/ram:DueDateTypeCode)='5'">3</xsl:when>
								<xsl:when test="fn:normalize-space(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax[1]/ram:DueDateTypeCode)='29'">35</xsl:when>
								<xsl:when test="fn:normalize-space(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax[1]/ram:DueDateTypeCode)='72'">432</xsl:when>
								<xsl:otherwise>
									<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax[1]/ram:DueDateTypeCode"/>
								</xsl:otherwise>
							</xsl:choose>
						</cbc:DescriptionCode>
					</xsl:if>
				</cac:InvoicePeriod>
			</xsl:if>
			<!-- Order reference -->
			<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerOrderReferencedDocument">
				<cac:OrderReference>
					<!-- Buyer order reference-->
					<cbc:ID>
						<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerOrderReferencedDocument/ram:IssuerAssignedID"/>
					</cbc:ID>
					<!-- Seller order reference-->
					<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerOrderReferencedDocument/ram:IssuerAssignedID">
						<cbc:SalesOrderID>
							<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerOrderReferencedDocument/ram:IssuerAssignedID"/>
						</cbc:SalesOrderID>
					</xsl:if>
				</cac:OrderReference>
			</xsl:if>
			<!-- Billing reference -->
			<xsl:for-each select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:InvoiceReferencedDocument">
				<cac:BillingReference>
					<cac:InvoiceDocumentReference>
						<!-- Invoice id-->
						<cbc:ID>
							<xsl:value-of select="ram:IssuerAssignedID"/>
						</cbc:ID>
						<!-- Invoice issue date-->
						<xsl:if test="ram:FormattedIssueDateTime/qdt:DateTimeString">
							<cbc:IssueDate>
								<xsl:value-of select="sfti:CIIDateToXSDDate(ram:FormattedIssueDateTime/qdt:DateTimeString/text())"/>
							</cbc:IssueDate>
						</xsl:if>
					</cac:InvoiceDocumentReference>
				</cac:BillingReference>
			</xsl:for-each>
			<!-- Despatch advice reference-->
			<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery/ram:DespatchAdviceReferencedDocument">
				<cac:DespatchDocumentReference>
					<cbc:ID>
						<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery/ram:DespatchAdviceReferencedDocument/ram:IssuerAssignedID"/>
					</cbc:ID>
				</cac:DespatchDocumentReference>
			</xsl:if>
			<!-- Receipt advice reference-->
			<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery/ram:ReceivingAdviceReferencedDocument">
				<cac:ReceiptDocumentReference>
					<cbc:ID>
						<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery/ram:ReceivingAdviceReferencedDocument/ram:IssuerAssignedID"/>
					</cbc:ID>
				</cac:ReceiptDocumentReference>
			</xsl:if>
			<!-- TENDER OR LOT REFERENCE in invoice-->
			<xsl:if test="$SourceIsInvoice">
			<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:AdditionalReferencedDocument[ram:TypeCode='50']">
				<cac:OriginatorDocumentReference>
					<cbc:ID>
						<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:AdditionalReferencedDocument[ram:TypeCode='50']/ram:IssuerAssignedID"/>
					</cbc:ID>
				</cac:OriginatorDocumentReference>
			</xsl:if>
			</xsl:if>
			<!--Contract REFERENCE -->
			<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:ContractReferencedDocument">
				<cac:ContractDocumentReference>
					<cbc:ID>
						<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:ContractReferencedDocument/ram:IssuerAssignedID"/>
					</cbc:ID>
				</cac:ContractDocumentReference>
			</xsl:if>
			<!--ADDITIONAL SUPPORTING DOCUMENTS -->
			<xsl:for-each select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:AdditionalReferencedDocument">
				<xsl:choose>
					<xsl:when test="ram:TypeCode='130'">
						<cac:AdditionalDocumentReference>
							<cbc:ID>
								<xsl:if test="ram:ReferenceTypeCode">
									<xsl:attribute name="schemeID"><xsl:value-of select="ram:ReferenceTypeCode"/></xsl:attribute>
								</xsl:if>
								<xsl:value-of select="ram:IssuerAssignedID"/>
							</cbc:ID>
							<cbc:DocumentTypeCode>
								<xsl:value-of select="ram:TypeCode"/>
							</cbc:DocumentTypeCode>
						</cac:AdditionalDocumentReference>
					</xsl:when>
					<xsl:when test="ram:TypeCode='50'">
						<!-- Nothing -->
					</xsl:when>
					<!-- not really correct to include repeatitions without typecode. not according to standard -->
					<xsl:when test="ram:TypeCode='916' or not(exists(ram:TypeCode))">
						<cac:AdditionalDocumentReference>
							<!--Supporting document reference  -->
							<cbc:ID>
								<xsl:value-of select="ram:IssuerAssignedID"/>
							</cbc:ID>
							<!--Supporting document description  -->
							<xsl:if test="ram:Name">
								<cbc:DocumentDescription>
									<xsl:value-of select="ram:Name"/>
								</cbc:DocumentDescription>
							</xsl:if>
							<xsl:if test="ram:URIID or ram:AttachmentBinaryObject">
								<cac:Attachment>
									<xsl:if test="ram:AttachmentBinaryObject">
										<!--Attached document  -->
										<cbc:EmbeddedDocumentBinaryObject>
											<xsl:attribute name="mimeCode"><xsl:value-of select="ram:AttachmentBinaryObject/@mimeCode"/></xsl:attribute>
											<xsl:if test="ram:AttachmentBinaryObject/@filename">
												<xsl:attribute name="filename"><xsl:value-of select="ram:AttachmentBinaryObject/@filename"/></xsl:attribute>
											</xsl:if>
											<xsl:value-of select="ram:AttachmentBinaryObject"/>
										</cbc:EmbeddedDocumentBinaryObject>
									</xsl:if>
									<!--External document location  -->
									<xsl:if test="ram:URIID">
										<cac:ExternalReference>
											<cbc:URI>
												<xsl:value-of select="ram:URIID"/>
											</cbc:URI>
										</cac:ExternalReference>
									</xsl:if>
								</cac:Attachment>
							</xsl:if>
						</cac:AdditionalDocumentReference>
					</xsl:when>
				</xsl:choose>
			</xsl:for-each>
			<!-- Project Reference (located here in invoices -->
			<xsl:if test="$SourceIsInvoice">
				<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SpecifiedProcuringProject">
					<cac:ProjectReference>
						<cbc:ID>
							<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SpecifiedProcuringProject/ram:ID"/>
						</cbc:ID>
					</cac:ProjectReference>
				</xsl:if>
			</xsl:if>
			<!-- Project Reference (located here in creditnote -->
			<xsl:if test="not($SourceIsInvoice)">
				<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SpecifiedProcuringProject">
					<cac:AdditionalDocumentReference>
						<cbc:ID>
							<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SpecifiedProcuringProject/ram:ID"/>
						</cbc:ID>
						<cbc:DocumentTypeCode>50</cbc:DocumentTypeCode>
					</cac:AdditionalDocumentReference>
				</xsl:if>
			</xsl:if>
			
			<!-- TENDER OR LOT REFERENCE located here in creditnote -->
			<xsl:if test="not($SourceIsInvoice)">
			<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:AdditionalReferencedDocument[ram:TypeCode='50']">
				<cac:OriginatorDocumentReference>
					<cbc:ID>
						<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:AdditionalReferencedDocument[ram:TypeCode='50']/ram:IssuerAssignedID"/>
					</cbc:ID>
				</cac:OriginatorDocumentReference>
			</xsl:if>		
			</xsl:if>	
			<!-- Seller -->
			<cac:AccountingSupplierParty>
				<cac:Party>
					<xsl:apply-templates select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty"/>
				</cac:Party>
			</cac:AccountingSupplierParty>
			<!-- Buyer -->
			<cac:AccountingCustomerParty>
				<cac:Party>
					<xsl:apply-templates select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty"/>
				</cac:Party>
			</cac:AccountingCustomerParty>
			<!-- Payee -->
			<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:PayeeTradeParty">
				<cac:PayeeParty>
					<xsl:apply-templates select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:PayeeTradeParty"/>
				</cac:PayeeParty>
			</xsl:if>
			<!-- TaxRepresentative -->
			<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTaxRepresentativeTradeParty">
				<cac:TaxRepresentativeParty>
					<xsl:apply-templates select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTaxRepresentativeTradeParty"/>
				</cac:TaxRepresentativeParty>
			</xsl:if>
			<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty or /rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery/ram:ActualDeliverySupplyChainEvent/ram:OccurrenceDateTime/udt:DateTimeString">
				<cac:Delivery>
					<!--Actual delivery date  -->
					<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery/ram:ActualDeliverySupplyChainEvent/ram:OccurrenceDateTime/udt:DateTimeString">
						<cbc:ActualDeliveryDate>
							<xsl:value-of select="sfti:CIIDateToXSDDate(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery/ram:ActualDeliverySupplyChainEvent/ram:OccurrenceDateTime/udt:DateTimeString/text())"/>
						</cbc:ActualDeliveryDate>
					</xsl:if>
					<!-- DELIVER TO ADDRESS  -->
					<cac:DeliveryLocation>
						<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty/ram:ID">
							<!-- Deliver to location identifier -->
							<cbc:ID>
								<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty/ram:ID/@schemeID">
									<xsl:attribute name="schemeID"><xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty/ram:ID/@schemeID"/></xsl:attribute>
								</xsl:if>
								<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty/ram:ID"/>
							</cbc:ID>
						</xsl:if>
						<xsl:for-each select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty/ram:GlobalID">
							<cac:PartyIdentification>
								<cbc:ID>
									<xsl:if test="./@schemeID">
										<xsl:attribute name="schemeID"><xsl:value-of select="./@schemeID"/></xsl:attribute>
									</xsl:if>
									<xsl:value-of select="."/>
								</cbc:ID>
							</cac:PartyIdentification>
						</xsl:for-each>
						<!--DELIVER TO ADDRESS  -->
						<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty/ram:PostalTradeAddress">
							<cac:Address>
								<xsl:apply-templates select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty/ram:PostalTradeAddress"/>
							</cac:Address>
						</xsl:if>
					</cac:DeliveryLocation>
					<!--Deliver to party name  -->
					<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty/ram:Name">
						<cac:DeliveryParty>
							<cac:PartyName>
								<cbc:Name>
									<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty/ram:Name"/>
								</cbc:Name>
							</cac:PartyName>
						</cac:DeliveryParty>
					</xsl:if>
				</cac:Delivery>
			</xsl:if>
			<!--PAYMENT INSTRUCTIONS  -->
			<xsl:for-each select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans">
				<cac:PaymentMeans>
					<!--Payment means type code  -->
					<cbc:PaymentMeansCode>
						<xsl:if test="ram:Information">
							<xsl:attribute name="name"><xsl:value-of select="ram:Information"/></xsl:attribute>
						</xsl:if>
						<xsl:value-of select="ram:TypeCode"/>
					</cbc:PaymentMeansCode>
					<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:DueDateDateTime/udt:DateTimeString">
						<xsl:if test="not($SourceIsInvoice)">
							<!-- Payment due date (credit notes)-->
							<cbc:PaymentDueDate>
								<xsl:value-of select="sfti:CIIDateToXSDDate(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:DueDateDateTime/udt:DateTimeString/text())"/>
							</cbc:PaymentDueDate>
						</xsl:if>
					</xsl:if>
					<!--Remittance information  -->
					<xsl:if test="../ram:PaymentReference">
						<cbc:PaymentID>
							<xsl:value-of select="../ram:PaymentReference"/>
						</cbc:PaymentID>
					</xsl:if>
					<!-- Card -->
					<xsl:if test="ram:ApplicableTradeSettlementFinancialCard">
						<!-- Card payment -->
						<cac:CardAccount>
							<!-- Payment card primary account number -->
							<cbc:PrimaryAccountNumberID>
								<xsl:value-of select="ram:ApplicableTradeSettlementFinancialCard/ram:ID"/>
							</cbc:PrimaryAccountNumberID>
							<!-- NetworkID was added in ISO 16931-3-2 syntax binding:   -->
							<cbc:NetworkID>NA</cbc:NetworkID>
							<!-- Payment card holder name -->
							<xsl:if test="ram:ApplicableTradeSettlementFinancialCard/ram:CardholderName">
								<cbc:HolderName>
									<xsl:value-of select="ram:ApplicableTradeSettlementFinancialCard/ram:CardholderName"/>
								</cbc:HolderName>
							</xsl:if>
						</cac:CardAccount>
					</xsl:if>
					<!-- Credit transfer -->
					<xsl:if test="ram:PayeePartyCreditorFinancialAccount">
						<cac:PayeeFinancialAccount>
							<xsl:choose>
								<xsl:when test="ram:PayeePartyCreditorFinancialAccount/ram:IBANID">
									<!--Payment account identifier  -->
									<cbc:ID>
										<xsl:value-of select="ram:PayeePartyCreditorFinancialAccount/ram:IBANID"/>
									</cbc:ID>
								</xsl:when>
								<!-- Payment account identifier -->
								<xsl:when test="ram:PayeePartyCreditorFinancialAccount/ram:ProprietaryID">
									<cbc:ID>
										<xsl:value-of select="ram:PayeePartyCreditorFinancialAccount/ram:ProprietaryID"/>
									</cbc:ID>
								</xsl:when>
							</xsl:choose>
							<!-- Payment account name -->
							<xsl:if test="ram:PayeePartyCreditorFinancialAccount/ram:AccountName">
								<cbc:Name>
									<xsl:value-of select="ram:PayeePartyCreditorFinancialAccount/ram:AccountName"/>
								</cbc:Name>
							</xsl:if>
							<!-- Payment service provider identifier -->
							<xsl:if test="ram:PayeeSpecifiedCreditorFinancialInstitution/ram:BICID">
								<cac:FinancialInstitutionBranch>
									<cbc:ID>
										<xsl:value-of select="ram:PayeeSpecifiedCreditorFinancialInstitution/ram:BICID"/>
									</cbc:ID>
								</cac:FinancialInstitutionBranch>
							</xsl:if>
						</cac:PayeeFinancialAccount>
					</xsl:if>
					<!-- Debit transfer -->
					<xsl:if test="ram:PayerPartyDebtorFinancialAccount">
						<cac:PaymentMandate>
							<!-- Mandate reference identifier -->
							<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:DirectDebitMandateID">
								<cbc:ID>
									<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:DirectDebitMandateID"/>
								</cbc:ID>
							</xsl:if>
							<!-- Debited account identifier -->
							<xsl:if test="ram:PayerPartyDebtorFinancialAccount/ram:IBANID">
								<cac:PayerFinancialAccount>
									<cbc:ID>
										<xsl:value-of select="ram:PayerPartyDebtorFinancialAccount/ram:IBANID"/>
									</cbc:ID>
								</cac:PayerFinancialAccount>
							</xsl:if>
						</cac:PaymentMandate>
					</xsl:if>
				</cac:PaymentMeans>
			</xsl:for-each>
			<!-- Payment terms-->
			<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:Description">
				<cac:PaymentTerms>
					<cbc:Note>
						<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:Description"/>
					</cbc:Note>
				</cac:PaymentTerms>
			</xsl:if>
			<!-- Allowance/Charge -->
			<xsl:apply-templates select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeAllowanceCharge"/>
			<!-- VAT BREAKDOWN -->
			<cac:TaxTotal>
				<!-- Invoice total VAT amount -->
				<cbc:TaxAmount currencyID="{$DocumentCurrencyID}">
					<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID=$DocumentCurrencyID]"/>
				</cbc:TaxAmount>
				<xsl:for-each select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax">
					<cac:TaxSubtotal>
						<!--VAT category taxable amount -->
						<cbc:TaxableAmount currencyID="{$DocumentCurrencyID}">
							<xsl:value-of select="ram:BasisAmount"/>
						</cbc:TaxableAmount>
						<!--VAT category tax amount -->
						<cbc:TaxAmount currencyID="{$DocumentCurrencyID}">
							<xsl:value-of select="ram:CalculatedAmount"/>
						</cbc:TaxAmount>
						<!-- tax category -->
						<xsl:apply-templates select="."/>
					</cac:TaxSubtotal>
				</xsl:for-each>
			</cac:TaxTotal>
			<!-- VAT accounting currency code -->
			<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:TaxCurrencyCode">
				<cac:TaxTotal>
					<!-- NB! Two occurrencies of TaxTotal only when tax in TaxCurrency is present -->
					<cbc:TaxAmount currencyID="{$TaxCurrencyID}">
						<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID=$TaxCurrencyID]"/>
					</cbc:TaxAmount>
				</cac:TaxTotal>
			</xsl:if>
			<cac:LegalMonetaryTotal>
				<!--Sum of all Invoice line net amounts in the Invoice. -->
				<cbc:LineExtensionAmount currencyID="{$DocumentCurrencyID}">
					<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:LineTotalAmount"/>
				</cbc:LineExtensionAmount>
				<!-- Invoice total amount without VAT -->
				<cbc:TaxExclusiveAmount currencyID="{$DocumentCurrencyID}">
					<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxBasisTotalAmount"/>
				</cbc:TaxExclusiveAmount>
				<!-- Invoice total amount with VAT -->
				<cbc:TaxInclusiveAmount currencyID="{$DocumentCurrencyID}">
					<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:GrandTotalAmount"/>
				</cbc:TaxInclusiveAmount>
				<!-- Sum of allowances on document level -->
				<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:AllowanceTotalAmount">
					<cbc:AllowanceTotalAmount currencyID="{$DocumentCurrencyID}">
						<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:AllowanceTotalAmount"/>
					</cbc:AllowanceTotalAmount>
				</xsl:if>
				<!-- Sum of charges on document level -->
				<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:ChargeTotalAmount">
					<cbc:ChargeTotalAmount currencyID="{$DocumentCurrencyID}">
						<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:ChargeTotalAmount"/>
					</cbc:ChargeTotalAmount>
				</xsl:if>
				<!-- Paid amount -->
				<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TotalPrepaidAmount">
					<cbc:PrepaidAmount currencyID="{$DocumentCurrencyID}">
						<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TotalPrepaidAmount"/>
					</cbc:PrepaidAmount>
				</xsl:if>
				<!-- rounding amount -->
				<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:RoundingAmount">
					<cbc:PayableRoundingAmount currencyID="{$DocumentCurrencyID}">
						<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:RoundingAmount"/>
					</cbc:PayableRoundingAmount>
				</xsl:if>
				<!-- Amount due for payment -->
				<cbc:PayableAmount currencyID="{$DocumentCurrencyID}">
					<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:DuePayableAmount"/>
				</cbc:PayableAmount>
			</cac:LegalMonetaryTotal>
			<!--
 CreditnoteLine 
						or 
					InvoiceLine -->
			<xsl:for-each select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem">
				<xsl:element name="{if ( $SourceIsInvoice ) then 'cac:InvoiceLine' else 'cac:CreditNoteLine'}">
					<!-- Invoice line identifier -->
					<cbc:ID>
						<xsl:value-of select="ram:AssociatedDocumentLineDocument/ram:LineID"/>
					</cbc:ID>
					<!-- Invoice line note -->
					<xsl:if test="ram:AssociatedDocumentLineDocument/ram:IncludedNote/ram:Content">
						<cbc:Note>
							<xsl:value-of select="ram:AssociatedDocumentLineDocument/ram:IncludedNote/ram:Content"/>
						</cbc:Note>
					</xsl:if>
					<!-- Quantity CreditnoteLine or InvoiceLine -->
					<xsl:element name="{if ( $SourceIsInvoice ) then 'cbc:InvoicedQuantity' else 'cbc:CreditedQuantity'}">
						<xsl:attribute name="unitCode"><xsl:value-of select="ram:SpecifiedLineTradeDelivery/ram:BilledQuantity/@unitCode"/></xsl:attribute>
						<xsl:value-of select="ram:SpecifiedLineTradeDelivery/ram:BilledQuantity"/>
					</xsl:element>
					<!-- Invoice line net amount -->
					<cbc:LineExtensionAmount currencyID="{$DocumentCurrencyID}">
						<xsl:value-of select="ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount"/>
					</cbc:LineExtensionAmount>
					<!-- Invoice line Buyer accounting reference -->
					<xsl:if test="ram:SpecifiedLineTradeSettlement/ram:ReceivableSpecifiedTradeAccountingAccount/ram:ID">
						<cbc:AccountingCost>
							<xsl:value-of select="ram:SpecifiedLineTradeSettlement/ram:ReceivableSpecifiedTradeAccountingAccount/ram:ID"/>
						</cbc:AccountingCost>
					</xsl:if>
					<!-- INVOICE LINE PERIOD -->
					<xsl:if test="ram:SpecifiedLineTradeSettlement/ram:BillingSpecifiedPeriod/ram:StartDateTime/udt:DateTimeString or ram:SpecifiedLineTradeSettlement/ram:BillingSpecifiedPeriod/ram:EndDateTime/udt:DateTimeString">
						<cac:InvoicePeriod>
							<xsl:if test="ram:SpecifiedLineTradeSettlement/ram:BillingSpecifiedPeriod/ram:StartDateTime/udt:DateTimeString">
								<cbc:StartDate>
									<xsl:value-of select="sfti:CIIDateToXSDDate(ram:SpecifiedLineTradeSettlement/ram:BillingSpecifiedPeriod/ram:StartDateTime/udt:DateTimeString/text())"/>
								</cbc:StartDate>
								<xsl:if test="ram:SpecifiedLineTradeSettlement/ram:BillingSpecifiedPeriod/ram:EndDateTime/udt:DateTimeString">
								</xsl:if>
								<cbc:EndDate>
									<xsl:value-of select="sfti:CIIDateToXSDDate(ram:SpecifiedLineTradeSettlement/ram:BillingSpecifiedPeriod/ram:EndDateTime/udt:DateTimeString/text())"/>
								</cbc:EndDate>
							</xsl:if>
						</cac:InvoicePeriod>
					</xsl:if>
					<!-- Invoice line object identifier -->
					<xsl:if test="ram:SpecifiedLineTradeAgreement/ram:BuyerOrderReferencedDocument/ram:LineID">
						<cac:OrderLineReference>
							<cbc:LineID>
								<xsl:value-of select="ram:SpecifiedLineTradeAgreement/ram:BuyerOrderReferencedDocument/ram:LineID"/>
							</cbc:LineID>
						</cac:OrderLineReference>
					</xsl:if>
					<!-- Invoice line object identifier -->
					<xsl:if test="ram:SpecifiedLineTradeSettlement/ram:AdditionalReferencedDocument/ram:IssuerAssignedID">
						<cac:DocumentReference>
							<!-- Invoice line object identifier -->
							<cbc:ID>
								<xsl:if test="ram:SpecifiedLineTradeSettlement/ram:AdditionalReferencedDocument/ram:ReferenceTypeCode">
									<xsl:attribute name="schemeID"><xsl:value-of select="ram:SpecifiedLineTradeSettlement/ram:AdditionalReferencedDocument/ram:ReferenceTypeCode"/></xsl:attribute>
								</xsl:if>
								<xsl:value-of select="ram:SpecifiedLineTradeSettlement/ram:AdditionalReferencedDocument/ram:IssuerAssignedID"/>
							</cbc:ID>
							<!-- Must be 130-->
							<cbc:DocumentTypeCode>
								<xsl:value-of select="ram:SpecifiedLineTradeSettlement/ram:AdditionalReferencedDocument/ram:TypeCode"/>
							</cbc:DocumentTypeCode>
						</cac:DocumentReference>
					</xsl:if>
					<!-- INVOICE LINE ALLOWANCES and CHARGES-->
					<xsl:apply-templates select="ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeAllowanceCharge"/>
					<cac:Item>
						<!-- Item description -->
						<xsl:if test="ram:SpecifiedTradeProduct/ram:Description">
							<cbc:Description>
								<xsl:value-of select="ram:SpecifiedTradeProduct/ram:Description"/>
							</cbc:Description>
						</xsl:if>
						<!-- Item name -->
						<cbc:Name>
							<xsl:value-of select="ram:SpecifiedTradeProduct/ram:Name"/>
						</cbc:Name>
						<!-- Item Buyer's identifier -->
						<xsl:if test="ram:SpecifiedTradeProduct/ram:BuyerAssignedID">
							<cac:BuyersItemIdentification>
								<cbc:ID>
									<xsl:value-of select="ram:SpecifiedTradeProduct/ram:BuyerAssignedID"/>
								</cbc:ID>
							</cac:BuyersItemIdentification>
						</xsl:if>
						<!-- Item Seller's identifier -->
						<xsl:if test="ram:SpecifiedTradeProduct/ram:SellerAssignedID">
							<cac:SellersItemIdentification>
								<cbc:ID>
									<xsl:value-of select="ram:SpecifiedTradeProduct/ram:SellerAssignedID"/>
								</cbc:ID>
							</cac:SellersItemIdentification>
						</xsl:if>
						<!-- Item standard identifier -->
						<xsl:if test="ram:SpecifiedTradeProduct/ram:GlobalID">
							<cac:StandardItemIdentification>
								<cbc:ID schemeID="{ram:SpecifiedTradeProduct/ram:GlobalID/@schemeID}">
									<xsl:value-of select="ram:SpecifiedTradeProduct/ram:GlobalID"/>
								</cbc:ID>
							</cac:StandardItemIdentification>
						</xsl:if>
						<!-- Item country of origin -->
						<xsl:if test="ram:SpecifiedTradeProduct/ram:OriginTradeCountry/ram:ID">
							<cac:OriginCountry>
								<cbc:IdentificationCode>
									<xsl:value-of select="ram:SpecifiedTradeProduct/ram:OriginTradeCountry/ram:ID"/>
								</cbc:IdentificationCode>
							</cac:OriginCountry>
						</xsl:if>
						<!-- Item country of origin -->
						<xsl:for-each select="ram:SpecifiedTradeProduct/ram:DesignatedProductClassification">
							<cac:CommodityClassification>
								<cbc:ItemClassificationCode>
									<xsl:if test="ram:ClassCode/@listID">
										<xsl:attribute name="listID"><xsl:value-of select="ram:ClassCode/@listID"/></xsl:attribute>
									</xsl:if>
									<xsl:if test="ram:ClassCode/@listVersionID">
										<xsl:attribute name="listVersionID"><xsl:value-of select="ram:ClassCode/@listVersionID"/></xsl:attribute>
									</xsl:if>
									<xsl:value-of select="ram:ClassCode"/>
								</cbc:ItemClassificationCode>
							</cac:CommodityClassification>
						</xsl:for-each>
						<cac:ClassifiedTaxCategory>
							<!-- Invoiced item VAT category code  -->
							<cbc:ID>
								<xsl:value-of select="ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:CategoryCode"/>
							</cbc:ID>
							<!-- Invoiced item VAT rate -->
							<xsl:if test="ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:RateApplicablePercent">
								<cbc:Percent>
									<xsl:value-of select="ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:RateApplicablePercent"/>
								</cbc:Percent>
							</xsl:if>
							<!-- Invoiced item VAT exemption reason code -->
							<xsl:if test="ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:ExemptionReasonCode">
								<cbc:TaxExemptionReasonCode>
									<xsl:value-of select="ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:ExemptionReasonCode"/>
								</cbc:TaxExemptionReasonCode>
							</xsl:if>
							<!-- Invoiced item VAT exemption reason  -->
							<xsl:if test="ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:ExemptionReason">
								<cbc:TaxExemptionReason>
									<xsl:value-of select="ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:ExemptionReason"/>
								</cbc:TaxExemptionReason>
							</xsl:if>
							<cac:TaxScheme>
								<cbc:ID>VAT</cbc:ID>
							</cac:TaxScheme>
						</cac:ClassifiedTaxCategory>
						<xsl:for-each select="ram:SpecifiedTradeProduct/ram:ApplicableProductCharacteristic">
							<!-- ITEM ATTRIBUTES -->
							<cac:AdditionalItemProperty>
								<cbc:Name>
									<xsl:value-of select="ram:Description"/>
								</cbc:Name>
								<cbc:Value>
									<xsl:value-of select="ram:Value"/>
								</cbc:Value>
							</cac:AdditionalItemProperty>
						</xsl:for-each>
					</cac:Item>
					<!-- PRICE DETAILS -->
					<cac:Price>
						<!-- Item net price -->
						<cbc:PriceAmount currencyID="{$DocumentCurrencyID}">
							<xsl:value-of select="ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:ChargeAmount"/>
						</cbc:PriceAmount>
						<!-- Item price base quantity -->
						<xsl:if test="ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:BasisQuantity">
							<cbc:BaseQuantity unitCode="{ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:BasisQuantity/@unitCode}">
								<xsl:value-of select="ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:BasisQuantity"/>
							</cbc:BaseQuantity>
						</xsl:if>
						<xsl:if test="ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:AppliedTradeAllowanceCharge		">
							<cac:AllowanceCharge>
								<cbc:ChargeIndicator>
									<xsl:value-of select="ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:AppliedTradeAllowanceCharge/ram:ChargeIndicator/udt:Indicator"/>
								</cbc:ChargeIndicator>
								<!-- Item price discount -->
								<xsl:if test="ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:AppliedTradeAllowanceCharge/ram:ActualAmount">
									<cbc:Amount currencyID="{$DocumentCurrencyID}">
										<xsl:value-of select="ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:AppliedTradeAllowanceCharge/ram:ActualAmount"/>
									</cbc:Amount>
								</xsl:if>
								<!-- Item gross price -->
								<xsl:if test="ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:ChargeAmount">
									<cbc:BaseAmount currencyID="{$DocumentCurrencyID}">
										<xsl:value-of select="ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:ChargeAmount"/>
									</cbc:BaseAmount>
								</xsl:if>
							</cac:AllowanceCharge>
						</xsl:if>
					</cac:Price>
				</xsl:element>
			</xsl:for-each>
		</xsl:element>
	</xsl:template>
	<xsl:template match="@*|node()" mode="OutputXML">
		<xsl:copy>
			<xsl:apply-templates select="@*|node()" mode="OutputXML"/>
		</xsl:copy>
	</xsl:template>
	<xsl:template match="ram:PostalTradeAddress">
		<xsl:if test="ram:LineOne">
			<!-- address line 1-->
			<cbc:StreetName>
				<xsl:value-of select="ram:LineOne"/>
			</cbc:StreetName>
		</xsl:if>
		<xsl:if test="ram:LineTwo">
			<!-- address line 2 -->
			<cbc:AdditionalStreetName>
				<xsl:value-of select="ram:LineTwo"/>
			</cbc:AdditionalStreetName>
		</xsl:if>
		<xsl:if test="ram:CityName">
			<!-- city -->
			<cbc:CityName>
				<xsl:value-of select="ram:CityName"/>
			</cbc:CityName>
		</xsl:if>
		<xsl:if test="ram:PostcodeCode">
			<!-- post code -->
			<cbc:PostalZone>
				<xsl:value-of select="ram:PostcodeCode"/>
			</cbc:PostalZone>
		</xsl:if>
		<xsl:if test="ram:CountrySubDivisionName">
			<!-- country subdivision -->
			<cbc:CountrySubentity>
				<xsl:value-of select="ram:CountrySubDivisionName"/>
			</cbc:CountrySubentity>
		</xsl:if>
		<xsl:if test="ram:LineThree">
			<!-- address line 3 -->
			<cac:AddressLine>
				<cbc:Line>
					<xsl:value-of select="ram:LineThree"/>
				</cbc:Line>
			</cac:AddressLine>
		</xsl:if>
		<xsl:if test="ram:CountryID">
			<!-- country code -->
			<cac:Country>
				<cbc:IdentificationCode>
					<xsl:value-of select="ram:CountryID"/>
				</cbc:IdentificationCode>
			</cac:Country>
		</xsl:if>
	</xsl:template>
	<!-- namn hanteras annorlunda på payee och tax -->
	<xsl:template match="ram:BuyerTradeParty | ram:SellerTradeParty | ram:PayeeTradeParty | ram:SellerTaxRepresentativeTradeParty">
		<!-- electronic address -->
		<xsl:if test="ram:URIUniversalCommunication/ram:URIID/@schemeID">
			<cbc:EndpointID>
				<xsl:attribute name="schemeID"><xsl:value-of select="ram:URIUniversalCommunication/ram:URIID/@schemeID"/></xsl:attribute>
				<xsl:value-of select="ram:URIUniversalCommunication/ram:URIID"/>
			</cbc:EndpointID>
		</xsl:if>
		<!-- party identifier -->
		<xsl:for-each select="ram:ID">
			<cac:PartyIdentification>
				<cbc:ID>
					<xsl:if test="./@schemeID">
						<xsl:attribute name="schemeID"><xsl:value-of select="./@schemeID"/></xsl:attribute>
					</xsl:if>
					<xsl:value-of select="."/>
				</cbc:ID>
			</cac:PartyIdentification>
		</xsl:for-each>
		<!-- party identifier global -->
		<xsl:for-each select="ram:GlobalID">
			<cac:PartyIdentification>
				<cbc:ID>
					<xsl:if test="./@schemeID">
						<xsl:attribute name="schemeID"><xsl:value-of select="./@schemeID"/></xsl:attribute>
					</xsl:if>
					<xsl:value-of select="."/>
				</cbc:ID>
			</cac:PartyIdentification>
		</xsl:for-each>
		<!-- Bank assigned creditor identifier -->
		<xsl:if test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:CreditorReferenceID and (local-name()='SellerTradeParty' or local-name()='PayeeTradeParty')">
			<cac:PartyIdentification>
				<cbc:ID>
					<xsl:attribute name="schemeID">SEPA</xsl:attribute>
					<xsl:value-of select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:CreditorReferenceID"/>
				</cbc:ID>
			</cac:PartyIdentification>
		</xsl:if>
		<xsl:if test="fn:local-name(.) = 'PayeeTradeParty' or fn:local-name(.) = 'SellerTaxRepresentativeTradeParty'">
			<!-- party trading name-->
			<cac:PartyName>
				<cbc:Name>
					<xsl:value-of select="ram:Name"/>
				</cbc:Name>
			</cac:PartyName>
		</xsl:if>
		<!-- party name-->
		<xsl:if test="ram:SpecifiedLegalOrganization/ram:TradingBusinessName">
			<cac:PartyName>
				<cbc:Name>
					<xsl:value-of select="ram:SpecifiedLegalOrganization/ram:TradingBusinessName"/>
				</cbc:Name>
			</cac:PartyName>
		</xsl:if>
		<!-- postal address-->
		<xsl:if test="ram:PostalTradeAddress">
			<cac:PostalAddress>
				<xsl:apply-templates select="ram:PostalTradeAddress"/>
			</cac:PostalAddress>
		</xsl:if>
		<xsl:for-each select="ram:SpecifiedTaxRegistration/ram:ID">
			<!-- VAT number -->
			<xsl:if test="./@schemeID='VA'">
				<cac:PartyTaxScheme>
					<cbc:CompanyID>
						<xsl:value-of select="."/>
					</cbc:CompanyID>
					<cac:TaxScheme>
						<cbc:ID>VAT</cbc:ID>
					</cac:TaxScheme>
				</cac:PartyTaxScheme>
			</xsl:if>
			<!-- tax registration identifier -->
			<xsl:if test="./@schemeID='FC'">
				<cac:PartyTaxScheme>
					<cbc:CompanyID>
						<xsl:value-of select="."/>
					</cbc:CompanyID>
					<cac:TaxScheme>
						<cbc:ID>TAX</cbc:ID>
					</cac:TaxScheme>
				</cac:PartyTaxScheme>
			</xsl:if>
		</xsl:for-each>
		<xsl:if test="fn:local-name(.) = 'BuyerTradeParty' or fn:local-name(.) = 'SellerTradeParty'">
			<cac:PartyLegalEntity>
				<!-- name -->
				<cbc:RegistrationName>
					<xsl:value-of select="ram:Name"/>
				</cbc:RegistrationName>
				<!--  legal registration identifier-->
				<xsl:if test="ram:SpecifiedLegalOrganization/ram:ID">
					<cbc:CompanyID>
						<xsl:if test="ram:SpecifiedLegalOrganization/ram:ID/@schemeID">
							<xsl:attribute name="schemeID"><xsl:value-of select="ram:SpecifiedLegalOrganization/ram:ID/@schemeID"/></xsl:attribute>
						</xsl:if>
						<xsl:value-of select="ram:SpecifiedLegalOrganization/ram:ID"/>
					</cbc:CompanyID>
				</xsl:if>
				<!--  additional legal information -->
				<xsl:if test="ram:Description">
					<cbc:CompanyLegalForm>
						<xsl:value-of select="ram:Description"/>
					</cbc:CompanyLegalForm>
				</xsl:if>
			</cac:PartyLegalEntity>
		</xsl:if>
		<xsl:if test="fn:local-name(.) = 'PayeeTradeParty'">
			<cac:PartyLegalEntity>
				<!--  legal registration identifier-->
				<xsl:if test="ram:SpecifiedLegalOrganization/ram:ID">
					<cbc:CompanyID>
						<xsl:if test="ram:SpecifiedLegalOrganization/ram:ID/@schemeID">
							<xsl:attribute name="schemeID"><xsl:value-of select="ram:SpecifiedLegalOrganization/ram:ID/@schemeID"/></xsl:attribute>
						</xsl:if>
						<xsl:value-of select="ram:SpecifiedLegalOrganization/ram:ID"/>
					</cbc:CompanyID>
				</xsl:if>
			</cac:PartyLegalEntity>
		</xsl:if>
		<xsl:if test="ram:DefinedTradeContact">
			<!-- contact point -->
			<cac:Contact>
				<xsl:if test="ram:DefinedTradeContact/ram:PersonName | ram:DefinedTradeContact/ram:DepartmentName">
					<cbc:Name>
						<xsl:value-of select="ram:DefinedTradeContact/ram:PersonName"/>
						<xsl:value-of select="ram:DefinedTradeContact/ram:DepartmentName"/>
					</cbc:Name>
				</xsl:if>
				<!-- contact telephone number-->
				<xsl:if test="ram:DefinedTradeContact/ram:TelephoneUniversalCommunication/ram:CompleteNumber">
					<cbc:Telephone>
						<xsl:value-of select="ram:DefinedTradeContact/ram:TelephoneUniversalCommunication/ram:CompleteNumber"/>
					</cbc:Telephone>
				</xsl:if>
				<!-- contact email address -->
				<xsl:if test="ram:DefinedTradeContact/ram:EmailURIUniversalCommunication/ram:URIID">
					<cbc:ElectronicMail>
						<xsl:value-of select="ram:DefinedTradeContact/ram:EmailURIUniversalCommunication/ram:URIID"/>
					</cbc:ElectronicMail>
				</xsl:if>
			</cac:Contact>
		</xsl:if>
	</xsl:template>
	<xsl:template match="ram:SpecifiedTradeAllowanceCharge">
		<cac:AllowanceCharge>
			<cbc:ChargeIndicator>
				<xsl:value-of select="ram:ChargeIndicator/udt:Indicator"/>
			</cbc:ChargeIndicator>
			<!-- Document level charge/allowance reason code -->
			<xsl:if test="ram:ReasonCode">
				<cbc:AllowanceChargeReasonCode>
					<xsl:value-of select="ram:ReasonCode"/>
				</cbc:AllowanceChargeReasonCode>
			</xsl:if>
			<!-- Document level charge/allowance reason-->
			<xsl:if test="ram:Reason">
				<cbc:AllowanceChargeReason>
					<xsl:value-of select="ram:Reason"/>
				</cbc:AllowanceChargeReason>
			</xsl:if>
			<!--Document level charge/allowance percentage -->
			<xsl:if test="ram:CalculationPercent">
				<cbc:MultiplierFactorNumeric>
					<xsl:value-of select="ram:CalculationPercent"/>
				</cbc:MultiplierFactorNumeric>
			</xsl:if>
			<!-- Document level charge/allowance amount -->
			<xsl:if test="ram:ActualAmount">
				<cbc:Amount currencyID="{$DocumentCurrencyID}">
					<xsl:value-of select="ram:ActualAmount"/>
				</cbc:Amount>
			</xsl:if>
			<!-- Document level charge/allowance base amount -->
			<xsl:if test="ram:BasisAmount">
				<cbc:BaseAmount currencyID="{$DocumentCurrencyID}">
					<xsl:value-of select="ram:BasisAmount"/>
				</cbc:BaseAmount>
			</xsl:if>
			<!-- Document level charge/allowance tax-->
			<xsl:if test="ram:CategoryTradeTax/ram:TypeCode">
				<xsl:apply-templates select="ram:CategoryTradeTax"/>
			</xsl:if>
		</cac:AllowanceCharge>
	</xsl:template>
	<xsl:template match="ram:ApplicableTradeTax | ram:CategoryTradeTax">
		<cac:TaxCategory>
			<!--  VAT category code-->
			<cbc:ID>
				<xsl:value-of select="ram:CategoryCode"/>
			</cbc:ID>
			<!--  VAT rate-->
			<xsl:if test="ram:RateApplicablePercent">
				<cbc:Percent>
					<xsl:value-of select="ram:RateApplicablePercent"/>
				</cbc:Percent>
			</xsl:if>
			<!-- VAT exemption reason code -->
			<xsl:if test="ram:ExemptionReasonCode">
				<cbc:TaxExemptionReasonCode>
					<xsl:value-of select="ram:ExemptionReasonCode"/>
				</cbc:TaxExemptionReasonCode>
			</xsl:if>
			<!-- VAT exemption reason  -->
			<xsl:if test="ram:ExemptionReason">
				<cbc:TaxExemptionReason>
					<xsl:value-of select="ram:ExemptionReason"/>
				</cbc:TaxExemptionReason>
			</xsl:if>
			<cac:TaxScheme>
				<cbc:ID>VAT</cbc:ID>
			</cac:TaxScheme>
		</cac:TaxCategory>
	</xsl:template>
</xsl:stylesheet>