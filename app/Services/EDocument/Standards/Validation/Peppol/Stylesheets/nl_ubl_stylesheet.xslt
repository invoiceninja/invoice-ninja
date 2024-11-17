<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
    xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
    xmlns:ubl="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">

    <xsl:output method="html" indent="yes"/>
    
    <xsl:template match="/ubl:Invoice">
        <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; margin: 40px; }
                    .header { margin-bottom: 20px; }
                    .section { margin: 20px 0; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
                    th { background-color: #f5f5f5; }
                    .amount { text-align: right; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>Invoice <xsl:value-of select="cbc:ID"/></h1>
                    <p>Date: <xsl:value-of select="cbc:IssueDate"/></p>
                    <p>Due Date: <xsl:value-of select="cbc:DueDate"/></p>
                </div>

                <!-- Supplier Details -->
                <div class="section">
                    <h2>From:</h2>
                    <p><xsl:value-of select="cac:AccountingSupplierParty/cac:Party/cac:PartyName/cbc:Name"/></p>
                    <p><xsl:value-of select="cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cbc:StreetName"/></p>
                    <p><xsl:value-of select="cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cbc:CityName"/></p>
                    <p>VAT: <xsl:value-of select="cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID"/></p>
                </div>

                <!-- Customer Details -->
                <div class="section">
                    <h2>To:</h2>
                    <p><xsl:value-of select="cac:AccountingCustomerParty/cac:Party/cac:PartyName/cbc:Name"/></p>
                    <p><xsl:value-of select="cac:AccountingCustomerParty/cac:Party/cac:PostalAddress/cbc:StreetName"/></p>
                    <p><xsl:value-of select="cac:AccountingCustomerParty/cac:Party/cac:PostalAddress/cbc:CityName"/></p>
                    <p>VAT: <xsl:value-of select="cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID"/></p>
                </div>

                <!-- Invoice Lines -->
                <div class="section">
                    <h2>Invoice Lines</h2>
                    <table>
                        <tr>
                            <th>ID</th>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Price</th>
                            <th>Amount</th>
                        </tr>
                        <xsl:for-each select="cac:InvoiceLine">
                            <tr>
                                <td><xsl:value-of select="cbc:ID"/></td>
                                <td><xsl:value-of select="cac:Item/cbc:Description"/></td>
                                <td class="amount"><xsl:value-of select="cbc:InvoicedQuantity"/></td>
                                <td><xsl:value-of select="cbc:InvoicedQuantity/@unitCode"/></td>
                                <td class="amount"><xsl:value-of select="cac:Price/cbc:PriceAmount"/></td>
                                <td class="amount"><xsl:value-of select="cbc:LineExtensionAmount"/></td>
                            </tr>
                        </xsl:for-each>
                    </table>
                </div>

                <!-- Totals -->
                <div class="section">
                    <h2>Totals</h2>
                    <table>
                        <tr>
                            <td>Net Amount:</td>
                            <td class="amount"><xsl:value-of select="cac:LegalMonetaryTotal/cbc:LineExtensionAmount"/></td>
                        </tr>
                        <tr>
                            <td>Tax Amount:</td>
                            <td class="amount"><xsl:value-of select="cac:TaxTotal/cbc:TaxAmount"/></td>
                        </tr>
                        <tr>
                            <td>Total Amount:</td>
                            <td class="amount"><xsl:value-of select="cac:LegalMonetaryTotal/cbc:PayableAmount"/></td>
                        </tr>
                    </table>
                </div>

                <!-- Payment Information -->
                <div class="section">
                    <h2>Payment Information</h2>
                    <p>Payment Terms: <xsl:value-of select="cac:PaymentTerms/cbc:Note"/></p>
                    <p>Payment ID: <xsl:value-of select="cac:PaymentMeans/cbc:PaymentID"/></p>
                    <p>Bank Account: <xsl:value-of select="cac:PaymentMeans/cac:PayeeFinancialAccount/cbc:ID"/></p>
                </div>
            </body>
        </html>
    </xsl:template>

</xsl:stylesheet>