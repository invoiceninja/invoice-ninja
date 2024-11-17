<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
    xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
    xmlns:ubl="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">
    
    <xsl:output method="html" version="5.0" encoding="UTF-8" indent="yes"/>
    
    <!-- Root template that matches any UBL document -->
    <xsl:template match="/*">
        <html>
            <head>
                <meta charset="utf-8"/>
                <meta name="viewport" content="width=device-width, initial-scale=1"/>
                <title><xsl:value-of select="local-name()"/> - <xsl:value-of select="cbc:ID"/></title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .header { margin-bottom: 20px; }
                    .section { margin: 20px 0; padding: 10px; border: 1px solid #ddd; }
                    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                    th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
                    th { background-color: #f5f5f5; }
                    .amount { text-align: right; }
                    dl { display: grid; grid-template-columns: auto 1fr; gap: 10px; }
                    dt { font-weight: bold; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1><xsl:value-of select="local-name()"/></h1>
                    <dl>
                        <dt>Document ID:</dt>
                        <dd><xsl:value-of select="cbc:ID"/></dd>
                        <dt>Issue Date:</dt>
                        <dd><xsl:value-of select="cbc:IssueDate"/></dd>
                        <xsl:if test="cbc:DueDate">
                            <dt>Due Date:</dt>
                            <dd><xsl:value-of select="cbc:DueDate"/></dd>
                        </xsl:if>
                    </dl>
                </div>

                <!-- Parties Section -->
                <div class="section">
                    <h2>Parties</h2>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <!-- Supplier -->
                        <div>
                            <h3>Supplier</h3>
                            <xsl:apply-templates select=".//cac:AccountingSupplierParty/cac:Party"/>
                        </div>
                        <!-- Customer -->
                        <div>
                            <h3>Customer</h3>
                            <xsl:apply-templates select=".//cac:AccountingCustomerParty/cac:Party"/>
                        </div>
                    </div>
                </div>

                <!-- Line Items -->
                <div class="section">
                    <h2>Line Items</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <xsl:apply-templates select=".//*[local-name()='InvoiceLine' or local-name()='CreditNoteLine']"/>
                        </tbody>
                    </table>
                </div>

                <!-- Totals -->
                <div class="section">
                    <h2>Totals</h2>
                    <xsl:apply-templates select="cac:LegalMonetaryTotal"/>
                </div>

                <!-- Payment Information -->
                <xsl:if test="cac:PaymentMeans">
                    <div class="section">
                        <h2>Payment Information</h2>
                        <xsl:apply-templates select="cac:PaymentMeans"/>
                    </div>
                </xsl:if>
            </body>
        </html>
    </xsl:template>

    <!-- Party Template -->
    <xsl:template match="cac:Party">
        <dl>
            <dt>Name:</dt>
            <dd><xsl:value-of select="cac:PartyName/cbc:Name"/></dd>
            <xsl:if test="cac:PostalAddress">
                <dt>Address:</dt>
                <dd>
                    <xsl:value-of select="cac:PostalAddress/cbc:StreetName"/><br/>
                    <xsl:value-of select="cac:PostalAddress/cbc:CityName"/><br/>
                    <xsl:value-of select="cac:PostalAddress/cbc:PostalZone"/><br/>
                    <xsl:value-of select="cac:PostalAddress/cac:Country/cbc:IdentificationCode"/>
                </dd>
            </xsl:if>
            <xsl:if test="cac:PartyTaxScheme/cbc:CompanyID">
                <dt>Tax ID:</dt>
                <dd><xsl:value-of select="cac:PartyTaxScheme/cbc:CompanyID"/></dd>
            </xsl:if>
        </dl>
    </xsl:template>

    <!-- Line Item Template -->
    <xsl:template match="*[local-name()='InvoiceLine' or local-name()='CreditNoteLine']">
        <tr>
            <td><xsl:value-of select="cbc:ID"/></td>
            <td><xsl:value-of select="cac:Item/cbc:Name"/></td>
            <td><xsl:value-of select="cac:Item/cbc:Description"/></td>
            <td class="amount"><xsl:value-of select="cbc:InvoicedQuantity|cbc:CreditedQuantity"/></td>
            <td class="amount"><xsl:value-of select="cac:Price/cbc:PriceAmount"/></td>
            <td class="amount"><xsl:value-of select="cbc:LineExtensionAmount"/></td>
        </tr>
    </xsl:template>

    <!-- Monetary Total Template -->
    <xsl:template match="cac:LegalMonetaryTotal">
        <dl>
            <dt>Net Amount:</dt>
            <dd class="amount"><xsl:value-of select="cbc:TaxExclusiveAmount"/></dd>
            <dt>Tax Amount:</dt>
            <dd class="amount"><xsl:value-of select="../cac:TaxTotal/cbc:TaxAmount"/></dd>
            <dt>Total Amount:</dt>
            <dd class="amount"><strong><xsl:value-of select="cbc:PayableAmount"/></strong></dd>
        </dl>
    </xsl:template>

    <!-- Payment Means Template -->
    <xsl:template match="cac:PaymentMeans">
        <dl>
            <dt>Payment Method:</dt>
            <dd><xsl:value-of select="cbc:PaymentMeansCode"/></dd>
            <xsl:if test="cbc:PaymentID">
                <dt>Payment ID:</dt>
                <dd><xsl:value-of select="cbc:PaymentID"/></dd>
            </xsl:if>
            <xsl:if test="cac:PayeeFinancialAccount/cbc:ID">
                <dt>Account:</dt>
                <dd><xsl:value-of select="cac:PayeeFinancialAccount/cbc:ID"/></dd>
            </xsl:if>
        </dl>
    </xsl:template>

</xsl:stylesheet>