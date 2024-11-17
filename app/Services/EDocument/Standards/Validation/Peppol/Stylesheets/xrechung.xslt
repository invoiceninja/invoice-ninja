<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:xs="http://www.w3.org/2001/XMLSchema"
  xmlns:xr="urn:ce.eu:en16931:2017:xoev-de:kosit:standard:xrechnung-1"
  xmlns:xrv="http://www.example.org/XRechnung-Viewer">

  <xsl:output indent="yes" method="html" encoding="UTF-8" />

  <xsl:decimal-format name="decimal" decimal-separator="," grouping-separator="." NaN="" />


  <!-- MAIN HTML -->
  <xsl:template match="/xr:invoice">

    <html lang="de">
      <head>
	<meta charset="UTF-8"/>
	<title>XRechnung</title>
	<meta name="viewport" content="width=device-width, user-scalable=yes, initial-scale=1.0"/>
	  <link href="xrechnung.css" rel="stylesheet" type="text/css"/>
      </head>
      <body>
	<form>
	  <div class="menue">
	    <div class="innen">
              <button type="button" class="tab" id="menueUebersicht" onclick="show(this);">Übersicht</button>
              <button type="button" class="tab" id="menueDetails" onclick="show(this);">Details</button>
              <button type="button" class="tab" id="menueZusaetze" onclick="show(this)">Zusätze</button>
              <button type="button" class="tab" id="menueAnlagen" onclick="show(this)">Anlagen</button>
              <button type="button" class="tab" id="menueLaufzettel" onclick="show(this)">Laufzettel</button>
	    </div>
	  </div>
	</form>
	<div class="inhalt">
	  <div class="innen">
	    <xsl:call-template name="uebersicht"/>
	    <xsl:call-template name="details"/>
	    <xsl:call-template name="zusaetze"/>
	    <xsl:call-template name="anlagen"/>
	    <xsl:call-template name="laufzettel"/>
	  </div>
	</div>
      </body>
      <script src='xrechnung.js'></script>
    </html>
  </xsl:template>


  <xsl:template name="uebersicht">
	    <div id="uebersicht" class="divShow">
	      <div class="haftungausschluss">Wir übernehmen keine Haftung für die Richtigkeit der Daten.</div>
	      <div class="boxtabelle boxtabelleZweispaltig">
		<div class="boxzeile">

		  <xsl:apply-templates select="./xr:BUYER"/>

		  <div class="boxabstand"></div>

		  <xsl:apply-templates select="./xr:SELLER"/>

		</div>
	      </div>

              <div class="boxtabelle boxabstandtop boxtabelleZweispaltig">
		<xsl:call-template name="uebersichtRechnungsinfo"/>
	      </div>

              <div class="boxtabelle boxabstandtop boxtabelleZweispaltig">
		<xsl:call-template name="uebersichtRechnungsuebersicht"/>
	      </div>

	      <div class="boxtabelle boxabstandtop boxtabelleZweispaltig">
		<xsl:apply-templates select="./xr:VAT_BREAKDOWN"/>
	      </div>

	      <div class="boxtabelle boxabstandtop boxtabelleZweispaltig">
		<xsl:apply-templates select="./xr:DOCUMENT_LEVEL_ALLOWANCES"/>
	      </div>

              <div class="boxtabelle boxabstandtop boxtabelleZweispaltig">
		<xsl:apply-templates select="./xr:DOCUMENT_LEVEL_CHARGES"/>
	      </div>

              <div class="boxtabelle boxabstandtop boxtabelleZweispaltig first">
		<div class="boxzeile">
		  <xsl:call-template name="uebersichtZahlungsinformationen"/>
		  <xsl:call-template name="uebersichtCard"/>
		</div>
	      </div>

              <div class="boxtabelle">
		<div class="boxzeile">
		  <xsl:call-template name="uebersichtLastschrift"/>
		  <xsl:call-template name="uebersichtUeberweisung"/>
		</div>
	      </div>

              <div class="boxtabelle boxabstandtop">
		<div class="boxzeile">
		  <xsl:apply-templates select="./xr:INVOICE_NOTE"/>
		</div>
	      </div>
	    </div>
  </xsl:template>


  <xsl:template name="uebersichtKaeufer" match="xr:BUYER">
            <div id="uebersichtKaeufer" class="box boxZweispaltig">
              <div title="BG-7" class="boxtitel">Informationen zum Käufer</div>
              <div class="boxtabelle boxinhalt borderSpacing">
                <div class="boxzeile">
                  <div class="boxdaten legende">Leitweg-ID:</div>
                  <div title="BT-10" class="boxdaten wert"><xsl:value-of select="../xr:Buyer_reference"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Name:</div>
                  <div title="BT-44" class="boxdaten wert"><xsl:value-of select="xr:Buyer_name"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Straße / Hausnummer:</div>
                  <div title="BT-50" class="boxdaten wert"><xsl:value-of select="xr:BUYER_POSTAL_ADDRESS/xr:Buyer_address_line_1"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Postfach:</div>
                  <div title="BT-51" class="boxdaten wert"><xsl:value-of select="xr:BUYER_POSTAL_ADDRESS/xr:Buyer_address_line_2"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Adresszusatz:</div>
                  <div title="BT-163" class="boxdaten wert"><xsl:value-of select="xr:BUYER_POSTAL_ADDRESS/xr:Buyer_address_line_3"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">PLZ:</div>
                  <div title="BT-53" class="boxdaten wert"><xsl:value-of select="xr:BUYER_POSTAL_ADDRESS/xr:Buyer_post_code"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Ort:</div>
                  <div title="BT-52" class="boxdaten wert"><xsl:value-of select="xr:BUYER_POSTAL_ADDRESS/xr:Buyer_city"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Land:</div>
                  <div title="BT-55" class="boxdaten wert"><xsl:value-of select="xr:BUYER_POSTAL_ADDRESS/xr:Buyer_country_code"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Kennung:</div>
                  <div title="BT-46" class="boxdaten wert"><xsl:value-of select="xr:Buyer_identifier"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Schema der Kennung:</div>
                  <div title="BT-46" class="boxdaten wert"><xsl:value-of select="xr:Buyer_identifier/@scheme_identifier"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Name:</div>
                  <div title="BT-56" class="boxdaten wert"><xsl:value-of select="xr:BUYER_CONTACT/xr:Buyer_contact_point"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Telefon:</div>
                  <div title="BT-57" class="boxdaten wert"><xsl:value-of select="xr:BUYER_CONTACT/xr:Buyer_contact_telephone_number"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">E-Mail-Adresse:</div>
                  <div title="BT-58" class="boxdaten wert"><xsl:value-of select="xr:BUYER_CONTACT/xr:Buyer_contact_email_address"/></div>
                </div>
              </div>
            </div>
  </xsl:template>


  <xsl:template name="uebersichtVerkaeufer" match="xr:SELLER">
            <div id="uebersichtVerkaeufer" class="box boxZweispaltig">
              <div title="BG-4" class="boxtitel">Informationen zum Verkäufer</div>
              <div class="boxtabelle boxinhalt borderSpacing">
                <div class="boxzeile">
                  <div class="boxdaten legende">Firmenname:</div>
                  <div title="BT-27" class="boxdaten wert"><xsl:value-of select="xr:Seller_name"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Straße / Hausnummer:</div>
                  <div title="BT-35" class="boxdaten wert"><xsl:value-of select="xr:SELLER_POSTAL_ADDRESS/xr:Seller_address_line_1"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Postfach:</div>
                  <div title="BT-36" class="boxdaten wert"><xsl:value-of select="xr:SELLER_POSTAL_ADDRESS/xr:Seller_address_line_2"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Adresszusatz:</div>
                  <div title="BT-162" class="boxdaten wert"><xsl:value-of select="xr:SELLER_POSTAL_ADDRESS/xr:Seller_address_line_3"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">PLZ:</div>
                  <div title="BT-38" class="boxdaten wert"><xsl:value-of select="xr:SELLER_POSTAL_ADDRESS/xr:Seller_post_code"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Ort:</div>
                  <div title="BT-37" class="boxdaten wert"><xsl:value-of select="xr:SELLER_POSTAL_ADDRESS/xr:Seller_city"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Bundesland:</div>
                  <div title="BT-39" class="boxdaten wert"><xsl:value-of select="xr:SELLER_POSTAL_ADDRESS/xr:Seller_country_subdivision"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Ländercode:</div>
                  <div title="BT-40" class="boxdaten wert"><xsl:value-of select="xr:SELLER_POSTAL_ADDRESS/xr:Seller_country_code"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Kennung:</div>
                  <div title="BT-29" class="boxdaten wert"><xsl:value-of select="xr:Seller_identifier"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Schema der Kennung:</div>
                  <div title="BT-29" class="boxdaten wert"><xsl:value-of select="xr:Seller_identifier/@scheme_identifier"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Name:</div>
                  <div title="BT-41" class="boxdaten wert"><xsl:value-of select="xr:SELLER_CONTACT/xr:Seller_contact_point"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Telefon:</div>
                  <div title="BT-42" class="boxdaten wert"><xsl:value-of select="xr:SELLER_CONTACT/xr:Seller_contact_telephone_number"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">E-Mail-Adresse:</div>
                  <div title="BT-43" class="boxdaten wert"><xsl:value-of select="xr:SELLER_CONTACT/xr:Seller_contact_email_address"/></div>
                </div>
              </div>
            </div>
  </xsl:template>


  <xsl:template name="uebersichtRechnungsinfo">
          <div class="boxzeile">
            <div id="uebersichtRechnungsinfo" class="box box1v2">
              <div class="boxtitel">Rechnungsdaten</div>
              <div class="boxtabelle boxinhalt">
                <div class="boxcell boxZweispaltig">
                  <div class="boxtabelle borderSpacing">
                    <div class="boxzeile">
                      <div class="boxdaten legende">Rechnungsnummer:</div>
                      <div title="BT-1" class="boxdaten wert"><xsl:value-of select="xr:Invoice_number"/></div>
                    </div>
                    <div class="boxzeile">
                      <div class="boxdaten legende">Rechnungsdatum:</div>
                      <div title="BT-2" class="boxdaten wert"><xsl:value-of select="format-date(xr:Invoice_issue_date,'[D].[M].[Y]')"/></div>
                    </div>
                    <div class="boxzeile">
                      <div class="boxdaten legende">Rechnungsart:</div>
                      <div title="BT-3" class="boxdaten wert"><xsl:value-of select="xr:Invoice_type_code"/></div>
                    </div>
                    <div class="boxzeile">
                      <div class="boxdaten legende">Währung:</div>
                      <div title="BT-5" class="boxdaten wert"><xsl:value-of select="xr:Invoice_currency_code"/></div>
                    </div>
                  </div>
                  <h4>Abrechnungszeitraum:</h4>
                  <div class="boxtabelle borderSpacing">
                    <div class="boxzeile">
                      <div class="boxdaten legende">von:</div>
                      <div title="BT-73" class="boxdaten wert"><xsl:value-of select="format-date(xr:DELIVERY_INFORMATION/xr:INVOICING_PERIOD/xr:Invoicing_period_start_date,'[D].[M].[Y]')"/></div>
                    </div>
                    <div class="boxzeile">
                      <div class="boxdaten legende">bis:</div>
                      <div  title="BT-74" class="boxdaten wert"><xsl:value-of select="format-date(xr:DELIVERY_INFORMATION/xr:INVOICING_PERIOD/xr:Invoicing_period_end_date,'[D].[M].[Y]')"/></div>
                    </div>
                  </div>
                </div>
                <div class="boxabstand"></div>
                <div class="boxcell boxZweispaltig">
                  <div class="boxtabelle borderSpacing">
                    <div class="boxzeile">
                      <div class="boxdaten legende">Projektnummer:</div>
                      <div title="BT-11" class="boxdaten wert"><xsl:value-of select="xr:Project_reference"/></div>
                    </div>
                    <div class="boxzeile">
                      <div class="boxdaten legende">Vertragsnummer:</div>
                      <div title="BT-12" class="boxdaten wert"><xsl:value-of select="xr:Contract_reference"/></div>
                    </div>
                    <div class="boxzeile">
                      <div class="boxdaten legende">Bestellnummer:</div>
                      <div title="BT-13" class="boxdaten wert"><xsl:value-of select="xr:Purchase_order_reference"/></div>
                    </div>
                    <div class="boxzeile">
                      <div class="boxdaten legende">Auftragsnummer:</div>
                      <div title="BT-14" class="boxdaten wert"><xsl:value-of select="xr:Sales_order_reference"/></div>
                    </div>
                  </div>
                  <h4>Vorausgegangene Rechnungen:</h4>
		  <xsl:apply-templates select="./xr:PRECEDING_INVOICE_REFERENCE"/>
                </div>
              </div>
            </div>
	  </div>
  </xsl:template>

  <xsl:template match="xr:PRECEDING_INVOICE_REFERENCE">
                  <div class="boxtabelle borderSpacing">
                    <div class="boxzeile">
                      <div class="boxdaten legende">Rechnungsnummer:</div>
                      <div title="BT-25" class="boxdaten wert"><xsl:value-of select="xr:Preceding_Invoice_reference"/></div>
                    </div>
                    <div class="boxzeile">
                      <div class="boxdaten legende">Rechnungsdatum:</div>
                      <div title="BT-26" class="boxdaten wert"><xsl:value-of select="(format-date,xr:Preceding_Invoice_issue_date,'[D].[M].[Y]')"/></div>
                    </div>
                  </div>
  </xsl:template>


  <xsl:template name="uebersichtRechnungsuebersicht">
          <div class="boxzeile">
            <div id="uebersichtRechnungsuebersicht" class="box">
              <div title="BG-22" class="boxtitel">Gesamtbeträge der Rechnung</div>
              <div class="boxtabelle boxinhalt">
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1">Summe aller Positionen</div>
                    <div class="boxdaten rechnungSp2 color2">netto</div>
                    <div title="BT-106" class="boxdaten rechnungSp3"><xsl:value-of select="format-number(xr:DOCUMENT_TOTALS/xr:Sum_of_Invoice_line_net_amount,'###.###,00','decimal')"/></div>
                  </div>
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1">Summe Nachlässe</div>
                    <div class="boxdaten rechnungSp2 color2">netto</div>
                    <div title="BT-107" class="boxdaten rechnungSp3"><xsl:value-of select="format-number(xr:Sum_of_allowances_on_document_level,'###.###,00','decimal')"/></div>
                  </div>
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1 paddingBottom line1Bottom">Summe Zuschläge</div>
                    <div class="boxdaten rechnungSp2 paddingBottom line1Bottom color2">netto</div>
                    <div title="BT-108" class="boxdaten rechnungSp3 paddingBottom line1Bottom"><xsl:value-of select="format-number(xr:DOCUMENT_TOTALS/xr:Sum_of_charges_on_document_level,'###.###,00','decimal')"/></div>
                  </div>
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1 paddingTop">Gesamtsumme</div>
                    <div class="boxdaten rechnungSp2 paddingTop color2">netto</div>
                    <div title="BT-109" class="boxdaten rechnungSp3 paddingTop"><xsl:value-of select="format-number(xr:DOCUMENT_TOTALS/xr:Invoice_total_amount_without_VAT,'###.###,00','decimal')"/></div>
                  </div>
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1">Summe Umsatzsteuer</div>
                    <div class="boxdaten rechnungSp2 color2"></div>
                    <div title="BT-110" class="boxdaten rechnungSp3"><xsl:value-of select="format-number(xr:DOCUMENT_TOTALS/xr:Invoice_total_VAT_amount,'###.###,00','decimal')"/></div>
                  </div>
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1 paddingBottom line1Bottom">Summe Umsatzsteuer in Abrechnungswährung</div>
                    <div class="boxdaten rechnungSp2 paddingBottom line1Bottom color2"></div>
                    <div title="BT-111" class="boxdaten rechnungSp3 paddingBottom line1Bottom"><xsl:value-of select="format-number(xr:DOCUMENT_TOTALS/xr:Invoice_total_VAT_amount_in_accounting_currency,'###.###,00','decimal')"/></div>
                  </div>
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1 paddingTop">Gesamtsumme</div>
                    <div class="boxdaten rechnungSp2 paddingTop color2">brutto</div>
                    <div title="BT-112" class="boxdaten rechnungSp3 paddingTop"><xsl:value-of select="format-number(xr:DOCUMENT_TOTALS/xr:Invoice_total_amount_with_VAT,'###.###,00','decimal')"/></div>
                  </div>
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1">Gezahlter Betrag</div>
                    <div class="boxdaten rechnungSp2 color2">brutto</div>
                    <div title="BT-113" class="boxdaten rechnungSp3"><xsl:value-of select="format-number(xr:DOCUMENT_TOTALS/xr:Paid_amount,'###.###,00','decimal')"/></div>
                  </div>
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1 paddingBottom line2Bottom">Rundungsbetrag</div>
                    <div class="boxdaten rechnungSp2 paddingBottom line2Bottom color2">brutto</div>
                    <div title="BT-114" class="boxdaten rechnungSp3 paddingBottom line2Bottom"><xsl:value-of select="format-number(xr:DOCUMENT_TOTALS/xr:Rounding_amount,'###.###,00','decimal')"/></div>
                  </div>
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1 paddingTop bold">Fälliger Betrag</div>
                    <div class="boxdaten rechnungSp2 paddingTop color2">brutto</div>
                    <div title="BT-115" class="boxdaten rechnungSp3 paddingTop bold"><xsl:value-of select="format-number(xr:DOCUMENT_TOTALS/xr:Amount_due_for_payment,'###.###,00','decimal')"/></div>
                  </div>
              </div>
            </div>
	  </div>
  </xsl:template>


  <xsl:template name="uebersichtUmsatzsteuer" match="xr:VAT_BREAKDOWN">
          <div class="boxzeile">
            <div id="uebersichtUmsatzsteuer" class="box">
              <div title="BG-23" class="boxtitel">Aufschlüsselung der Umsatzsteuer auf Ebene der Rechnung</div>
              <div class="boxtabelle boxinhalt">
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1 bold">Umsatzsteuerkategorie: <span title="BT-118"><xsl:value-of select="xr:VAT_category_code"/></span></div>
                    <div class="boxdaten rechnungSp2"></div>
                    <div class="boxdaten rechnungSp3"></div>
                  </div>
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1">Gesamtsumme</div>
                    <div class="boxdaten rechnungSp2 color2">netto</div>
                    <div title="BT-116" class="boxdaten rechnungSp3"><xsl:value-of select="format-number(xr:VAT_category_taxable_amount,'###.###,00','decimal')"/></div>
                  </div>
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1 line1Bottom">Umsatzsteuersatz</div>
                    <div class="boxdaten rechnungSp2 color2 line1Bottom"></div>
                    <div title="BT-119" class="boxdaten rechnungSp3 line1Bottom"><xsl:value-of select="xr:VAT_category_rate"/>%</div>
                  </div>
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1">Umsatzsteuerbetrag</div>
                    <div class="boxdaten rechnungSp2 color2"></div>
                    <div title="BT-117" class="boxdaten rechnungSp3 bold"><xsl:value-of select="format-number(xr:VAT_category_tax_amount,'###.###,00','decimal')"/></div>
                  </div>
              </div>

              <div class="grund">
                <div>Befreiungsgrund: <span title="BT-120" class="bold"><xsl:value-of select="xr:VAT_exemption_reason_text"/></span></div>
                <div>Kennung für den Befreiungsgrund: <span title="BT-121" class="bold"><xsl:value-of select="xr:VAT_exemption_reason_code"/></span></div>
              </div>
            </div>
	  </div>
  </xsl:template>


  <xsl:template name="uebersichtNachlass" match="xr:DOCUMENT_LEVEL_ALLOWANCES">
          <div class="boxzeile">
            <div id="uebersichtNachlass" class="box">
              <div title="BG-20" class="boxtitel">Nachlass auf Ebene der Rechnung</div>
              <div class="boxtabelle boxinhalt">
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1 bold">Umsatzsteuerkategorie des Nachlasses: <span title="BT-95"><xsl:value-of select="xr:Document_level_allowance_VAT_category_code"/></span></div>
                    <div class="boxdaten rechnungSp2"></div>
                    <div class="boxdaten rechnungSp3"></div>
                  </div>
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1">Grundbetrag</div>
                    <div class="boxdaten rechnungSp2 color2">netto</div>
                    <div title="BT-93" class="boxdaten rechnungSp3"><xsl:value-of select="format-number(xr:Document_level_allowance_base_amount,'###.###,00','decimal')"/></div>
                  </div>
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1 line1Bottom">Prozentsatz</div>
                    <div class="boxdaten rechnungSp2 color2 line1Bottom"></div>
                    <div title="BT-94" class="boxdaten rechnungSp3 line1Bottom"><xsl:value-of select="xr:Document_level_allowance_percentage"/>%</div>
                  </div>
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1">Nachlass</div>
                    <div class="boxdaten rechnungSp2 color2">netto</div>
                    <div title="BT-92" class="boxdaten rechnungSp3 bold"><xsl:value-of select="format-number(xr:Document_level_allowance_amount,'###.###,00','decimal')"/></div>
                  </div>
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1">Umsatzsteuersatz des Nachlasses</div>
                    <div class="boxdaten rechnungSp2 color2"></div>
                    <div title="BT-96" class="boxdaten rechnungSp3"><xsl:value-of select="xr:Document_level_allowance_VAT_rate"/></div>
                  </div>
              </div>
              <div class="grund">
                <div>Grund für den Nachlass: <span title="BT-97" class="bold"><xsl:value-of select="xr:Document_level_allowance_reason"/></span></div>
                <div>Document level allowance reason code: <span title="BT-98" class="bold"><xsl:value-of select="xr:Document_level_allowance_reason_code"/></span></div>
              </div>
            </div>
	  </div>
	  <div class="boxabstand"></div>
  </xsl:template>


  <xsl:template name="uebersichtZuschlaege" match="xr:DOCUMENT_LEVEL_CHARGES">
          <div class="boxzeile">
            <div id="uebersichtZuschlaege" class="box">
              <div title="BG-21" class="boxtitel">Zuschlag auf Ebene der Rechnung</div>
              <div class="boxtabelle boxinhalt">
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1 bold">Umsatzsteuerkategorie des Zuschlages: <span title="BT-102"><xsl:value-of select="xr:Document_level_charge_VAT_category_code"/></span></div>
                    <div class="boxdaten rechnungSp2"></div>
                    <div class="boxdaten rechnungSp3"></div>
                  </div>
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1">Grundbetrag</div>
                    <div class="boxdaten rechnungSp2 color2">netto</div>
                    <div title="BT-100" class="boxdaten rechnungSp3"><xsl:value-of select="format-number(xr:Document_level_charge_base_amount,'###.###,00','decimal')"/></div>
                  </div>
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1 line1Bottom">Prozentsatz</div>
                    <div class="boxdaten rechnungSp2 color2 line1Bottom"></div>
                    <div title="BT-101" class="boxdaten rechnungSp3 line1Bottom"><xsl:value-of select="xr:Document_level_charge_percentage"/>%</div>
                  </div>
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1">Zuschlag</div>
                    <div class="boxdaten rechnungSp2 color2">netto</div>
                    <div title="BT-99" class="boxdaten rechnungSp3 bold"><xsl:value-of select="format-number(xr:Document_level_charge_amount,'###.###,00','decimal')"/></div>
                  </div>
                  <div class="rechnungsZeile">
                    <div class="boxdaten rechnungSp1">Umsatzsteuersatz des Zuschlages</div>
                    <div class="boxdaten rechnungSp2 color2"></div>
                    <div title="BT-103" class="boxdaten rechnungSp3"><xsl:value-of select="xr:Document_level_charge_VAT_rate"/></div>
                  </div>
              </div>
              <div class="grund">
                <div>Grund für den Zuschlag: <span title="BT-104" class="bold"><xsl:value-of select="xr:Document_level_charge_reason"/></span></div>
                <div>Document level charge reason code: <span title="BT-105" class="bold"><xsl:value-of select="xr:Document_level_charge_reason_code"/></span></div>
              </div>
            </div>
	  </div>
	  <div class="boxabstand"></div>
  </xsl:template>


  <xsl:template name="uebersichtZahlungsinformationen">
            <div id="uebersichtZahlungsinformationen" class="box subBox">
              <div title="" class="boxtitel">Zahlungsdaten</div>
              <div class="boxtabelle boxinhalt borderSpacing">
                <div class="boxzeile">
                  <div class="boxdaten legende">Skonto; weitere Zahlungsbedingungen:</div>
                  <div title="BT-20" class="boxdaten wert"><xsl:value-of select="xr:Payment_terms"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Fälligkeitsdatum:</div>
                  <div title="BT-9" class="boxdaten wert"><xsl:value-of select="format-date(xr:Payment_due_date,'[D].[M].[Y]')"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Code für das Zahlungsmittel:</div>
                  <div title="BT-81" class="boxdaten wert"><xsl:value-of select="xr:PAYMENT_INSTRUCTIONS/xr:Payment_means_type_code"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Zahlungsmittel:</div>
                  <div title="BT-82" class="boxdaten wert"><xsl:value-of select="xr:PAYMENT_INSTRUCTIONS/xr:Payment_means_text"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Verwendungszweck:</div>
                  <div title="BT-83" class="boxdaten wert"><xsl:value-of select="xr:PAYMENT_INSTRUCTIONS/xr:Remittance_information"/></div>
                </div>
              </div>
            </div>
  </xsl:template>


  <xsl:template name="uebersichtCard">
            <div id="uebersichtCard" class="box subBox">
              <div title="BG-18" class="boxtitel boxtitelSub">Karteninformation</div>
              <div class="boxtabelle boxinhalt borderSpacing">
                <div class="boxzeile">
                  <div class="boxdaten legende">Kartennummer:</div>
                  <div title="BT-87" class="boxdaten wert"><xsl:value-of select="xr:PAYMENT_INSTRUCTIONS/xr:PAYMENT_CARD_INFORMATION/xr:Payment_card_primary_account_number"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Karteninhaber:</div>
                  <div title="BT-88" class="boxdaten wert"><xsl:value-of select="xr:PAYMENT_INSTRUCTIONS/xr:PAYMENT_CARD_INFORMATION/xr:Payment_card_holder_name"/></div>
                </div>
              </div>
            </div>
  </xsl:template>


  <xsl:template name="uebersichtLastschrift">
            <div id="uebersichtLastschrift" class="box subBox">
              <div title="BG-19" class="boxtitel boxtitelSub">Lastschrift</div>
              <div class="boxtabelle boxinhalt borderSpacing">
                <div class="boxzeile">
                  <div class="boxdaten legende">Mandatsreferenznr.:</div>
                  <div title="BT-89" class="boxdaten wert"><xsl:value-of select="xr:PAYMENT_INSTRUCTIONS/xr:DIRECT_DEBIT/xr:Mandate_reference_identifier"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">IBAN:</div>
                  <div title="BT-91" class="boxdaten wert"><xsl:value-of select="xr:PAYMENT_INSTRUCTIONS/xr:DIRECT_DEBIT/xr:Debited_account_identifier"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Gläubiger-ID:</div>
                  <div title="BT-90" class="boxdaten wert"><xsl:value-of select="xr:PAYMENT_INSTRUCTIONS/xr:DIRECT_DEBIT/xr:Bank_assigned_creditor_identifier"/></div>
                </div>
              </div>
            </div>
  </xsl:template>


  <xsl:template name="uebersichtUeberweisung">
            <div id="uebersichtUeberweisung" class="box subBox">
              <div title="BG-17" class="boxtitel boxtitelSub">Überweisung</div>
              <div class="boxtabelle boxinhalt borderSpacing">
                 <div class="boxzeile">
                    <div class="boxdaten legende">Kontoinhaber:</div>
                    <div title="BT-85" class="boxdaten wert"><xsl:value-of select="xr:PAYMENT_INSTRUCTIONS/xr:CREDIT_TRANSFER/xr:Payment_account_name"/></div>
                  </div>
                  <div class="boxzeile">
                    <div class="boxdaten legende">IBAN:</div>
                    <div title="BT-84" class="boxdaten wert"><xsl:value-of select="xr:PAYMENT_INSTRUCTIONS/xr:CREDIT_TRANSFER/xr:Payment_account_identifier"/></div>
                  </div>
                  <div class="boxzeile">
                    <div class="boxdaten legende">BIC:</div>
                    <div title="BT-86" class="boxdaten wert"><xsl:value-of select="xr:PAYMENT_INSTRUCTIONS/xr:CREDIT_TRANSFER/xr:Payment_service_provider_identifier"/></div>
                  </div>
              </div>
            </div>
  </xsl:template>


  <xsl:template name="uebersichtBemerkungen" match="xr:INVOICE_NOTE">
            <div id="uebersichtBemerkungen" class="box">
              <div title="BG-1" class="boxtitel">Bemerkung zur Rechnung</div>
              <div class="boxtabelle boxinhalt borderSpacing">
                 <div class="boxzeile">
                    <div class="boxdaten legende">Betreff:</div>
                    <div title="BT-21" class="boxdaten wert"><xsl:value-of select="xr:Invoice_note_subject_code"/></div>
                  </div>
                  <div class="boxzeile">
                    <div class="boxdaten legende">Bemerkung:</div>
                    <div title="BT-22" class="boxdaten wert"><xsl:value-of select="xr:Invoice_note"/></div>
                  </div>
              </div>
            </div>
  </xsl:template>


  <xsl:template name="details">
      <div id="details" class="divHide">
        <div class="haftungausschluss">Wir übernehmen keine Haftung für die Richtigkeit der Daten.</div>
	<xsl:apply-templates select="./xr:INVOICE_LINE"/> <!-- many -->
      </div>
  </xsl:template>


  <xsl:template match="xr:INVOICE_LINE">
        <div class="boxtabelle boxabstandtop boxtabelleZweispaltig first">
          <div class="boxzeile">
            <div class="box subBox">
              <div title="BT-126" class="boxtitel">Position <xsl:value-of select="xr:Invoice_line_identifier"/></div>
              <div class="boxtabelle boxinhalt borderSpacing">
                  <div class="boxzeile">
                    <div class="boxdaten legende">Freitext:</div>
                    <div title="BT-127" class="boxdaten wert"><xsl:value-of select="xr:Invoice_line_note"/></div>
                  </div>
                  <div class="boxzeile">
                    <div class="boxdaten legende">Objektkennung:</div>
                    <div title="BT-128" class="boxdaten wert"><xsl:value-of select="xr:Invoice_line_object_identifier"/></div>
                  </div>
                  <div class="boxzeile">
                    <div class="boxdaten legende">Schema der Objektkennung:</div>
                    <div title="BT-128" class="boxdaten wert"><xsl:value-of select="xr:Invoice_line_object_identifier/@scheme_identifier"/></div>
                  </div>
                  <div class="boxzeile">
                    <div class="boxdaten legende">Nummer der Auftragsposition:</div>
                    <div title="BT-132" class="boxdaten wert"><xsl:value-of select="xr:Referenced_purchase_order_line_reference"/></div>
                  </div>
                  <div class="boxzeile">
                    <div class="boxdaten legende">Kontierungshinweis:</div>
                    <div title="BT-133" class="boxdaten wert"><xsl:value-of select="xr:Invoice_line_Buyer_accounting_reference"/></div>
                  </div>
                  <h4 title="BG-26">Abrechnungszeitraum:</h4>
                  <div class="boxzeile">
                    <div class="boxdaten legende">von:</div>
                    <div title="BT-134" class="boxdaten wert"><xsl:value-of select="format-date(xr:INVOICE_LINE_PERIOD/xr:Invoice_line_period_start_date,'[D].[M].[Y]')"/></div>
                  </div>
                  <div class="boxzeile">
                    <div class="boxdaten legende">bis:</div>
                    <div title="BT-135" class="boxdaten wert"><xsl:value-of select="format-date(xr:INVOICE_LINE_PERIOD/xr:Invoice_line_period_end_date,'[D].[M].[Y]')"/></div>
                  </div>
              </div>
            </div>
            <div class="box subBox">
              <div title="BG-29" class="boxtitel boxtitelSub">Preiseinzelheiten</div>
              <div class="boxtabelle boxinhalt">
                <div class="rechnungsZeile">
                  <div class="boxdaten detailSp1 color2">Menge</div>
                  <div title="BT-129" class="boxdaten detailSp2"><xsl:value-of select="xr:Invoiced_quantity"/></div>
                </div>
                <div class="rechnungsZeile">
                  <div class="boxdaten detailSp1 color2">Einheit</div>
                  <div title="BT-130" class="boxdaten detailSp2"><xsl:value-of select="xr:Invoiced_quantity_unit_of_measure_code"/></div>
                </div>
                <div class="rechnungsZeile">
                  <div class="boxdaten detailSp1 line1Bottom color2">Preis pro Einheit (netto)</div>
                  <div title="BT-146" class="boxdaten detailSp2 line1Bottom"><xsl:value-of select="format-number(xr:PRICE_DETAILS/xr:Item_net_price,'###.###,00','decimal')"/></div>
                </div>
                <div class="rechnungsZeile">
                  <div class="boxdaten detailSp1 color2">Gesamtpreis (netto)</div>
                  <div title="BT-131" class="boxdaten detailSp2 bold"><xsl:value-of select="format-number(xr:Invoice_line_net_amount,'###.###,00','decimal')"/></div>
                </div>
              </div>
              <div class="boxtabelle boxinhalt noPaddingTop borderSpacing">
                <div class="boxzeile">
                  <div class="boxdaten legende ">Rabatt (netto):</div>
                  <div title="BT-147" class="boxdaten wert"><xsl:value-of select="format-number(xr:PRICE_DETAILS/xr:Item_price_discount,'###.###,00','decimal')"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende ">Listenpreis (netto):</div>
                  <div title="BT-148" class="boxdaten wert"><xsl:value-of select="format-number(xr:PRICE_DETAILS/xr:Item_gross_price,'###.###,00','decimal')"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende ">Anzahl der Einheit:</div>
                  <div title="BT-149" class="boxdaten wert"><xsl:value-of select="xr:PRICE_DETAILS/xr:Item_price_base_quantity"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende ">Code der Maßeinheit:</div>
                  <div title="BT-150" class="boxdaten wert"><xsl:value-of select="xr:PRICE_DETAILS/xr:Item_price_base_quantity_unit_of_measure"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende ">Umsatzsteuer:</div>
                  <div title="BT-151" class="boxdaten wert"><xsl:value-of select="xr:LINE_VAT_INFORMATION/xr:Invoiced_item_VAT_category_code"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende ">Umsatzsteuersatz in Prozent:</div>
                  <div title="BT-152" class="boxdaten wert"><xsl:value-of select="xr:LINE_VAT_INFORMATION/xr:Invoiced_item_VAT_rate"/>%</div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="boxtabelle">
          <div class="boxzeile">
            <div class="box subBox">
              <div  title="BG-27" class="boxtitel boxtitelSub">Nachlässe auf Ebene der Rechnungsposition</div>
              <div class="boxtabelle boxinhalt ">
                <div class="rechnungsZeile">
                  <div class="boxdaten detailSp1 color2">Grundbetrag (netto)</div>
                  <div title="BT-137" class="boxdaten detailSp2"><xsl:value-of select="format-number(xr:INVOICE_LINE_ALLOWANCES/xr:Invoice_line_allowance_base_amount,'###.###,00','decimal')"/></div>
                </div>
                <div class="rechnungsZeile">
                  <div class="boxdaten detailSp1 line1Bottom color2">Prozentsatz</div>
                  <div title="BT-138" class="boxdaten detailSp2 line1Bottom"><xsl:value-of select="xr:INVOICE_LINE_ALLOWANCES/xr:Invoice_line_allowance_percentage"/>%</div>
                </div>
                <div class="rechnungsZeile">
                  <div class="boxdaten detailSp1 color2">Nachlass (netto)</div>
                  <div title="BT-136" class="boxdaten detailSp2 bold"><xsl:value-of select="format-number(xr:INVOICE_LINE_ALLOWANCES/xr:Invoice_line_allowance_amount,'###.###,00','decimal')"/></div>
                </div>
              </div>
              <div class="grundDetail">
                <div class="color2">Grund des Nachlasses: <span title="BT-139" class="bold"><xsl:value-of select="xr:INVOICE_LINE_ALLOWANCES/xr:Invoice_line_allowance_reason"/></span></div>
                <div class="color2">Code für den Nachlassgrund: <span title="BT-140" class="bold"><xsl:value-of select="xr:INVOICE_LINE_ALLOWANCES/xr:Invoice_line_allowance_reason_code"/></span></div>
              </div>
            </div>
            <div class="box subBox">
              <div title="BG-28" class="boxtitel boxtitelSub">Zuschläge auf Ebene der Rechnungsposition</div>
              <div class="boxtabelle boxinhalt ">
                <div class="rechnungsZeile">
                  <div class="boxdaten detailSp1 color2">Grundbetrag (netto)</div>
                  <div title="BT-142" class="boxdaten detailSp2"><xsl:value-of select="format-number(xr:INVOICE_LINE_CHARGES/xr:Invoice_line_charge_base_amount,'###.###,00','decimal')"/></div>
                </div>
                <div class="rechnungsZeile">
                  <div class="boxdaten detailSp1 line1Bottom color2">Prozentsatz</div>
                  <div title="BT-143" class="boxdaten detailSp2 line1Bottom"><xsl:value-of select="xr:INVOICE_LINE_CHARGES/xr:Invoice_line_charge_percentage"/>%</div>
                </div>
                <div class="rechnungsZeile">
                  <div class="boxdaten detailSp1 color2">Zuschlag (netto)</div>
                  <div title="BT-141" class="boxdaten detailSp2 bold"><xsl:value-of select="format-number(xr:INVOICE_LINE_CHARGES/xr:Invoice_line_charge_amount,'###.###,00','decimal')"/></div>
                </div>
              </div>
              <div class="grundDetail">
                <div class="color2">Grund des Zuschlags: <span title="BT-144" class="bold"><xsl:value-of select="xr:INVOICE_LINE_CHARGES/xr:Invoice_line_charge_reason"/></span></div>
                <div class="color2">Code für den Zuschlagsgrund: <span title="BT-145" class="bold"><xsl:value-of select="xr:INVOICE_LINE_CHARGES/xr:Invoice_line_charge_reason_code"/></span></div>
              </div>
            </div>
          </div>
        </div>
        <div class="boxtabelle">
          <div class="boxzeile">
            <div class="box subBox">
              <div  title="BG-27" class="boxtitel boxtitelSub">Artikelinformationen</div>
              <div class="boxtabelle boxinhalt ">
                <div class="boxzeile">
                 <div class="boxcell boxZweispaltig">
                  <div class="boxtabelle borderSpacing">
                    <div class="boxzeile">
                      <div class="boxdaten legende ">Bezeichnung:</div>
                      <div title="BT-153" class="boxdaten wert bold"><xsl:value-of select="xr:ITEM_INFORMATION/xr:Item_name"/></div>
                    </div>
                    <div class="boxzeile">
                      <div class="boxdaten legende ">Beschreibung:</div>
                      <div title="BT-154" class="boxdaten wert"><xsl:value-of select="xr:ITEM_INFORMATION/xr:Item_description"/></div>
                    </div>
                    <div class="boxzeile">
                      <div class="boxdaten legende ">Artikelnummer:</div>
                      <div title="BT-155" class="boxdaten wert"><xsl:value-of select="xr:ITEM_INFORMATION/xr:Item_Sellers_identifier"/></div>
                    </div>
                    <div class="boxzeile">
                      <div class="boxdaten legende ">Artikelkennung des Käufers:</div>
                      <div title="BT-156" class="boxdaten wert"><xsl:value-of select="xr:ITEM_INFORMATION/xr:Item_Buyers_identifier"/></div>
                    </div>
                    <h4 title="BG-32">Eigenschaften des Artikels:</h4>
		    <xsl:apply-templates select="xr:ITEM_INFORMATION/xr:ITEM_ATTRIBUTES" />
                  </div>
                </div>
                <div class="boxabstand"></div>
                <div class="boxcell boxZweispaltig">
                 <div class="boxtabelle borderSpacing">
                  <div class="boxzeile">
                    <div class="boxdaten legende ">Artikelkennung:</div>
                    <div title="BT-157" class="boxdaten wert"><xsl:value-of select="xr:ITEM_INFORMATION/xr:Item_standard_identifier"/></div>
                  </div>
                  <div class="boxzeile">
                    <div class="boxdaten legende ">Schema der Artikelkennung:</div>
                    <div title="BT-157" class="boxdaten wert"><xsl:value-of select="xr:ITEM_INFORMATION/xr:Item_standard_identifier/@scheme_identifier"/></div>
                  </div>
                  <div class="boxzeile">
                    <div class="boxdaten legende ">Code der Artikelklassifizierung:</div>
                    <div title="BT-158" class="boxdaten wert"><xsl:value-of select="xr:ITEM_INFORMATION/xr:Item_classification_identifier"/></div>
                  </div>
                  <div class="boxzeile">
                    <div class="boxdaten legende ">Kennung zur Bildung des Schemas:</div>
                    <div title="BT-158" class="boxdaten wert"><xsl:value-of select="xr:ITEM_INFORMATION/xr:Item_classification_identifier/@scheme_identifier"/></div>
                  </div>
                  <div class="boxzeile">
                    <div class="boxdaten legende ">Version zur Bildung des Schemas:</div>
                    <div title="BT-158" class="boxdaten wert"><xsl:value-of select="xr:ITEM_INFORMATION/xr:Item_classification_identifier/@scheme_version_identifier"/></div>
                  </div>
                  <div class="boxzeile">
                    <div class="boxdaten legende ">Code des Herkunftslandes:</div>
                    <div title="BT-159" class="boxdaten wert"><xsl:value-of select="xr:ITEM_INFORMATION/xr:Item_country_of_origin"/></div>
                  </div>
                </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
  </xsl:template>


  <xsl:template name="eigenschaft" match="xr:ITEM_ATTRIBUTES">
    <div class="boxzeile">
      <div title="BT-160" class="boxdaten legende "><xsl:value-of select="xr:Item_attribute_name"/></div>
      <div title="BT-161" class="boxdaten wert"><xsl:value-of select="xr:Item_attribute_value"/></div>
    </div>
  </xsl:template>


  <xsl:template name="zusaetze">
      <div id="zusaetze" class="divHide">
        <div class="haftungausschluss">Wir übernehmen keine Haftung für die Richtigkeit der Daten.</div>
        <div class="boxtabelle boxtabelleZweispaltig">
          <div class="boxzeile">
	    <xsl:apply-templates select="./xr:SELLER" mode="zusaetze"/>
            <div class="boxabstand"></div>
	    <xsl:apply-templates select="./xr:SELLER_TAX_REPRESENTATIVE_PARTY"/>
	  </div>
	</div>
        <div class="boxtabelle boxabstandtop boxtabelleZweispaltig">
          <div class="boxzeile">
	    <xsl:apply-templates select="./xr:BUYER" mode="zusaetze"/>
            <div class="boxabstand"></div>
	    <xsl:apply-templates select="./xr:DELIVERY_INFORMATION"/>
	  </div>
	</div>
        <div class="boxtabelle boxabstandtop boxtabelleZweispaltig">
          <div class="boxzeile">
	    <xsl:call-template name="zusaetzeVertrag"/>
            <div class="boxabstand"></div>
	    <xsl:apply-templates select="./xr:PAYEE"/>
	  </div>
	</div>
      </div>
  </xsl:template>


  <xsl:template name="zusaetzeVerkaeufer" match="xr:SELLER" mode="zusaetze">
            <div id="zusaetzeVerkaeufer" class="box boxZweispaltig">
              <div title="BG-4" class="boxtitel">Informationen zum Verkäufer</div>
              <div class="boxtabelle boxinhalt borderSpacing">
                <div class="boxzeile">
                  <div class="boxdaten legende">Abweichender Handelsname:</div>
                  <div title="BT-28" class="boxdaten wert"><xsl:value-of select="xr:Seller_trading_name"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Bundesland:</div>
                  <div title="BT-39" class="boxdaten wert"><xsl:value-of select="xr:SELLER_POSTAL_ADDRESS/xr:Seller_country_subdivision"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Elektronische Adresse:</div>
                  <div title="BT-34" class="boxdaten wert"><xsl:value-of select="xr:Seller_electronic_address"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Schema der elektronischen Adresse:</div>
                  <div title="BT-34" class="boxdaten wert"><xsl:value-of select="xr:Seller_electronic_address/@scheme_identifier"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Register-/Registriernummer:</div>
                  <div title="BT-30" class="boxdaten wert"><xsl:value-of select="xr:Seller_legal_registration_identifier"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Umsatzsteuer-ID:</div>
                  <div title="BT-31" class="boxdaten wert"><xsl:value-of select="xr:Seller_VAT_identifier"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Steuernummer:</div>
                  <div title="BT-32" class="boxdaten wert"><xsl:value-of select="xr:Seller_tax_registration_identifier"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Schema der Steuernummer:</div>
                  <div title="BT-32" class="boxdaten wert"><xsl:value-of select="xr:Seller_tax_registration_identifier/@scheme_identifier"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Weitere rechtliche Informationen:</div>
                  <div title="BT-33" class="boxdaten wert"><xsl:value-of select="xr:Seller_additional_legal_information"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Code der Umsatzsteuerwährung:</div>
                  <div title="BT-6" class="boxdaten wert"><xsl:value-of select="../xr:VAT_accounting_currency_code"/></div>
                </div>
              </div>
            </div>
  </xsl:template>


  <xsl:template name="zusaetzeSteuervertreter" match="xr:SELLER_TAX_REPRESENTATIVE_PARTY">
            <div id="zusaetzeSteuervertreter" class="box boxZweispaltig">
              <div title="BG-11" class="boxtitel">Steuervertreter des Verkäufers</div>
              <div class="boxtabelle boxinhalt borderSpacing">
                <div class="boxzeile">
                  <div class="boxdaten legende">Name:</div>
                  <div title="BT-62" class="boxdaten wert"><xsl:value-of select="xr:Seller_tax_representative_name"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Straße / Hausnummer:</div>
                  <div title="BT-64" class="boxdaten wert"><xsl:value-of select="xr:SELLER_TAX_REPRESENTATIVE_POSTAL_ADDRESS/xr:Tax_representative_address_line_1"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Postfach:</div>
                  <div title="BT-65" class="boxdaten wert"><xsl:value-of select="xr:SELLER_TAX_REPRESENTATIVE_POSTAL_ADDRESS/xr:Tax_representative_address_line_2"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Adresszusatz:</div>
                  <div title="BT-164" class="boxdaten wert"><xsl:value-of select="xr:SELLER_TAX_REPRESENTATIVE_POSTAL_ADDRESS/xr:Tax_representative_address_line_3"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">PLZ:</div>
                  <div title="BT-67" class="boxdaten wert"><xsl:value-of select="xr:SELLER_TAX_REPRESENTATIVE_POSTAL_ADDRESS/xr:Tax_representative_post_code"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Ort:</div>
                  <div title="BT-66" class="boxdaten wert"><xsl:value-of select="xr:SELLER_TAX_REPRESENTATIVE_POSTAL_ADDRESS/xr:Tax_representative_city"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Bundesland:</div>
                  <div title="BT-68" class="boxdaten wert"><xsl:value-of select="xr:SELLER_TAX_REPRESENTATIVE_POSTAL_ADDRESS/xr:Tax_representative_country_subdivision"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Ländercode:</div>
                  <div title="BT-69" class="boxdaten wert"><xsl:value-of select="xr:SELLER_TAX_REPRESENTATIVE_POSTAL_ADDRESS/xr:Tax_representative_country_code"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Umsatzsteuer-ID:</div>
                  <div title="BT-63" class="boxdaten wert"><xsl:value-of select="xr:Seller_tax_representative_VAT_identifier"/></div>
                </div>
              </div>
            </div>
  </xsl:template>


  <xsl:template name="zusaetzeKaeufer" match="xr:BUYER" mode="zusaetze">
            <div id="zusaetzeKaeufer" class="box boxZweispaltig">
              <div title="BG-7" class="boxtitel">Informationen zum Käufer</div>
              <div class="boxtabelle boxinhalt borderSpacing">
                <div class="boxzeile">
                  <div class="boxdaten legende">Abweichender Handelsname:</div>
                  <div title="BT-45" class="boxdaten wert"><xsl:value-of select="xr:Buyer_trading_name"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Bundesland:</div>
                  <div title="BT-54" class="boxdaten wert"><xsl:value-of select="xr:BUYER_POSTAL_ADDRESS/xr:Buyer_country_subdivision"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Elektronische Adresse:</div>
                  <div title="BT-49" class="boxdaten wert"><xsl:value-of select="xr:Buyer_electronic_address"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Schema der elektronischen Adresse:</div>
                  <div title="BT-49" class="boxdaten wert"><xsl:value-of select="xr:Buyer_electronic_address/@scheme_identifier"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Register-/Registriernummer:</div>
                  <div title="BT-47" class="boxdaten wert"><xsl:value-of select="xr:Buyer_legal_registration_identifier"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Schema der Register-/Registriernummer:</div>
                  <div title="BT-47" class="boxdaten wert"><xsl:value-of select="xr:Buyer_legal_registration_identifier/@scheme_identifier"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Umsatzsteuer-ID:</div>
                  <div title="BT-48" class="boxdaten wert"><xsl:value-of select="xr:Buyer_VAT_identifier"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Abrechnungsdatum der Umsatzsteuer:</div>
                  <div title="BT-7" class="boxdaten wert"><xsl:value-of select="format-date(xr:Value_added_tax_point_date,'[D].[M].[Y]')"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Code des Umsatzsteuer-Abrechnungsdatums:</div>
                  <div title="BT-8" class="boxdaten wert"><xsl:value-of select="xr:Value_added_tax_point_date_code"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Kontierungsinformation:</div>
                  <div title="BT-19" class="boxdaten wert"><xsl:value-of select="../xr:Buyer_accounting_reference"/></div>
                </div>
              </div>
            </div>
  </xsl:template>


  <xsl:template name="zusaetzeLieferung" match="xr:DELIVERY_INFORMATION">
            <div id="zusaetzeLieferung" class="box boxZweispaltig">
              <div title="BG-13" class="boxtitel">Lieferinformationen</div>
              <div class="boxtabelle boxinhalt borderSpacing">
                <div class="boxzeile">
                  <div class="boxdaten legende">Kennung des Lieferorts:</div>
                  <div title="BT-71" class="boxdaten wert"><xsl:value-of select="xr:Deliver_to_location_identifier"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Schema der Kennung:</div>
                  <div title="BT-71" class="boxdaten wert"><xsl:value-of select="xr:Deliver_to_location_identifier/@scheme_identifier"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Lieferdatum:</div>
                  <div title="BT-72" class="boxdaten wert"><xsl:value-of select="format-date(xr:Actual_delivery_date,'[D].[M].[Y]')"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Name des Empfängers:</div>
                  <div title="BT-70" class="boxdaten wert"><xsl:value-of select="xr:Deliver_to_party_name"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Straße / Hausnummer:</div>
                  <div title="BT-75" class="boxdaten wert"><xsl:value-of select="xr:DELIVER_TO_ADDRESS/xr:Deliver_to_address_line_1"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Postfach:</div>
                  <div title="BT-76" class="boxdaten wert"><xsl:value-of select="xr:DELIVER_TO_ADDRESS/xr:Deliver_to_address_line_2"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Adresszusatz:</div>
                  <div title="BT-165" class="boxdaten wert"><xsl:value-of select="xr:DELIVER_TO_ADDRESS/xr:Deliver_to_address_line_3"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">PLZ:</div>
                  <div title="BT-78" class="boxdaten wert"><xsl:value-of select="xr:DELIVER_TO_ADDRESS/xr:Deliver_to_post_code"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Ort:</div>
                  <div title="BT-77" class="boxdaten wert"><xsl:value-of select="xr:DELIVER_TO_ADDRESS/xr:Deliver_to_city"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Bundesland:</div>
                  <div title="BT-79" class="boxdaten wert"><xsl:value-of select="xr:DELIVER_TO_ADDRESS/xr:Deliver_to_country_subdivision"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Land:</div>
                  <div title="BT-80" class="boxdaten wert"><xsl:value-of select="xr:DELIVER_TO_ADDRESS/xr:Deliver_to_country_code"/></div>
                </div>
              </div>
            </div>
  </xsl:template>


  <xsl:template name="zusaetzeVertrag">
            <div id="zusaetzeVertrag" class="box boxZweispaltig">
              <div class="boxtitel">Informationen zum Vertrag</div>
              <div class="boxtabelle boxinhalt borderSpacing">
                <div class="boxzeile">
                  <div class="boxdaten legende">Vergabenummer:</div>
                  <div title="BT-17" class="boxdaten wert"><xsl:value-of select="xr:Tender_or_lot_reference"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Kennung der Empfangsbestätigung:</div>
                  <div title="BT-15" class="boxdaten wert"><xsl:value-of select="xr:Receiving_advice_reference"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Kennung der Versandanzeige:</div>
                  <div title="BT-16" class="boxdaten wert"><xsl:value-of select="xr:Despatch_advice_reference"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Prozesskennung:</div>
                  <div title="BT-23" class="boxdaten wert"><xsl:value-of select="xr:PROCESS_CONTROL/xr:Business_process_type"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Spezifikationskennung:</div>
                  <div title="BT-24" class="boxdaten wert"><xsl:value-of select="xr:PROCESS_CONTROL/xr:Specification_identifier"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Objektkennung:</div>
                  <div title="BT-18" class="boxdaten wert"><xsl:value-of select="xr:Invoiced_object_identifier"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Schema der Objektkennung:</div>
                  <div title="BT-18" class="boxdaten wert"><xsl:value-of select="xr:Invoiced_object_identifier/@scheme_identifier"/></div>
                </div>
              </div>
            </div>
  </xsl:template>


  <xsl:template name="zusaetzeZahlungsempfaenger" match="xr:PAYEE">
            <div id="zusaetzeZahlungsempfaenger" class="box boxZweispaltig">
              <div title="BG-10" class="boxtitel">Vom Verkäufer abweichender Zahlungsempfänger</div>
              <div class="boxtabelle boxinhalt borderSpacing">
                <div class="boxzeile">
                  <div class="boxdaten legende">Name:</div>
                  <div title="BT-59" class="boxdaten wert"><xsl:value-of select="xr:Payee_name"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Kennung:</div>
                  <div title="BT-60" class="boxdaten wert"><xsl:value-of select="xr:Payee_identifier"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Schema der Kennung:</div>
                  <div title="BT-60" class="boxdaten wert"><xsl:value-of select="xr:Payee_identifier/@scheme_identifier"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Register-/Registriernummer:</div>
                  <div title="BT-61" class="boxdaten wert"><xsl:value-of select="xr:Payee_legal_registration_identifier"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Schema der Register-/Registriernummer:</div>
                  <div title="BT-61" class="boxdaten wert"><xsl:value-of select="xr:Payee_legal_registration_identifier/@scheme_identifier"/></div>
                </div>
              </div>
            </div>
  </xsl:template>


  <xsl:template name="anlagen">
      <div id="anlagen" class="divHide">
        <div class="haftungausschluss">Wir übernehmen keine Haftung für die Richtigkeit der Daten.</div>
        <div class="boxtabelle boxabstandtop">
          <div class="boxzeile">
            <div id="anlagenListe" class="box">
              <div title="BG-24"  class="boxtitel">Rechnungsbegründende Unterlagen</div>
	      <xsl:apply-templates select="./xr:ADDITIONAL_SUPPORTING_DOCUMENTS"/>
	    </div>
	  </div>
	</div>
      </div>
  </xsl:template>


  <xsl:template match="xr:ADDITIONAL_SUPPORTING_DOCUMENTS">
              <div class="boxtabelle boxinhalt borderSpacing">
                <div class="boxzeile">
                  <div class="boxdaten legende">Kennung:</div>
                  <div title="BT-122" class="boxdaten wert"><xsl:value-of select="xr:Supporting_document_reference"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Beschreibung:</div>
                  <div title="BT-123" class="boxdaten wert"><xsl:value-of select="xr:Supporting_document_description"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Verweis (z.B. Internetadresse):</div>
                  <div title="BT-124" class="boxdaten wert"><a href="{xr:External_document_location}"><xsl:value-of select="xr:External_document_location"/></a></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Anhangsdokument:</div>
                  <div title="BT-125" class="boxdaten wert">
		    <a href="#"
		       onClick="downloadData('{xr:Supporting_document_reference}')">Öffnen</a>
		  </div>
		  <div id="{xr:Supporting_document_reference}"
		       mimetype="{xr:Attached_document/@mime_code}"
		       filename="{xr:Attached_document/@filename}"
		       style="display:none;"
		       ><xsl:value-of select="xr:Attached_document"/></div>

                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Format des Anhangdokuments:</div>
                  <div title="BT-125" class="boxdaten wert"><xsl:value-of select="xr:Attached_document/@mime_code"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Name des Anhangsdokuments:</div>
                  <div title="BT-125" class="boxdaten wert"><xsl:value-of select="xr:Attached_document/@filename"/></div>
                </div>
              </div>
  </xsl:template>


  <xsl:template name="laufzettel">
      <div id="laufzettel" class="divHide">
        <div class="boxtabelle boxabstandtop">
          <div class="boxzeile">
            <div id="laufzettelHistorie" class="box">
              <div class="boxtitel">Bearbeitungshistorie</div>
              <xsl:apply-templates select="./xrv:laufzettel/xrv:laufzettelEintrag"/>
            </div>
          </div>
         </div>
        </div>
  </xsl:template>


  <xsl:template match="xrv:laufzettelEintrag">
              <div class="boxtabelle boxinhalt borderSpacing">
                <div class="boxzeile">
                  <div class="boxdaten legende">Datum/Uhrzeit:</div>
                  <div class="boxdaten wert"><xsl:value-of select="format-dateTime(xrv:zeitstempel,'[D].[M].[Y] [H]:[m]:[s]')"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Betreff:</div>
                  <div class="boxdaten wert"><xsl:value-of select="xrv:betreff"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Text:</div>
                  <div class="boxdaten wert"><xsl:value-of select="xrv:text"/></div>
                </div>
                <div class="boxzeile">
                  <div class="boxdaten legende">Details:</div>
                  <div class="boxdaten wert"><xsl:value-of select="xrv:details"/></div>
                </div>
              </div>
  </xsl:template>

</xsl:stylesheet>