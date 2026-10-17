# Sample document field analysis (Mushak 6.3 challan + Square Invoice Report)

Sources: `mushak-6.3-purchase-challan.png`, `square-invoice-report-summary.png`, `square-invoice-report-lines.png` (the two invoice photos are the same invoice, 2610050652897). Transcriptions live next to this file:

- `purchase-challan-6.3.csv` (17 lines), `purchase-challan-6.3-header.json`
- `sales-invoice-report.csv` (9 invoice + 1 bonus + 3 combo-bonus lines), `sales-invoice-report-header.json`
- `products-seed.csv` (24 candidates: 17 from the challan, 7 sales-only)

All numbers below were recomputed with Python `Decimal` from the CSV files, not from memory.

## 1. Key findings that affect the app

1. **Printed unit price is rounded; value is not qty × printed price.** Square computes value = qty × TP ÷ (1+SD) ÷ 1.15 and prints the unit price rounded to 2 dp. Example: line 1, 1440 × 23.83 = 34,315.20 but value = 34,309.44 (true unit 23.8260). Import validation needs a tolerance of about `qty × 0.005` on purchase lines. A fixed ±0.01 would flag 16 of the 17 lines.
2. **Line totals are clean VAT-inclusive trade prices (TP).** Total ÷ qty gives round numbers: 27.40, 128.00, 273.00, 315.00, 39.45, 62.30, 107.00, 308.00, 310.00, 200.00, 215.00, 700.00, 272.00, 166.50, 208.33, 333.33. The ex-VAT value is calculated backwards from that TP.
3. **The sales invoice prices are the same VAT+SD-inclusive TP.** Revive lotion 300 ml is bought at 243.48 ex-VAT (TP 308.00) and sold at 308.00. Petroleum jelly 100 ml is bought at 93.04 (TP 107.00) and sold at 107.00. So the distributor's margin comes from commission, discounts and schemes, not from the price difference, which matches the brief's commission model. **Decision needed:** should sales revenue be treated as VAT-inclusive (the VAT is already paid at source by Square) when it is compared with cost ex-VAT?
4. **SD (supplementary duty) is present on some products:** Lipgel 5%, Revive 10%, Spring 15%. VAT is charged on value + SD. **Decision needed:** brief rule 2 only excludes VAT from cost. SD is normally not creditable, so the proposal is stock cost = value + SD when `vat_recoverable` is on.
5. **Combo Bonus Policy (Price Disc.) 419.7312 = 4.00% × (invoice lines 2 + 3 + 4 = 10,493.28), exactly.** Those lines are the petroleum jelly lines with no line discount. Line 5, also petroleum jelly, already has exactly 4% as its line discount. This is a strong hypothesis, not printed. The Slab Adjustment (419.73) equals the combo bonus policy amount rounded to 2 dp.
6. **Net total is rounded to whole taka:** 21,716.86 − 419.73 = 21,297.13, but 21,297.00 is printed and confirmed by the amount in words. That is a −0.13 rounding difference. It may be truncation or round-half rather than nearest-taka rounding; this one sample cannot tell them apart. The app needs a `rounding_adjustment` field.
7. **Free goods appear in two forms:** (a) an unnumbered "Bonus" row under the parent line (bonus qty 3 in the Bonus Qty column; the Amount column prints the word "Bonus"), and (b) an "Invoice Combo Bonus" section with unit price shown, amount 0.00, and its own Sub Total of 0.00. Total free qty is 42, worth 1,829.04 at the printed unit prices. Ratios: 3 per 36, 24 per 288 and 4 per 48 are all 1 per 12. For soap it is 11 per 72, which is not a clean ratio.
8. **Neither document prints product codes.** Matching has to rely on name + pack + alias. The challan has English names and the Square invoice has Bangla names, so the same SKU arrives under two different names. The alias table must link both, as with P08 and P09 in the seed file.
9. Line discounts on lotions are ≈7.69% (139.38 / 1812.00 = 7.6921%, 142.16 / 1848.00 = 7.6926%), close to 1/13. Neither exactly 1/13 nor any per-unit rounding rule reproduces both to the paisa, so the app should **store the printed discount amount, not recompute it from a percentage**.
10. The golden test's 23.83 is the printed (rounded) unit price of challan line 1, Chaka Ball Soap. The exact cost is 23.8260.

## 2. Fields each Excel import template must cover

### Purchase: header sheet

| Field | Example | Notes |
|---|---|---|
| doc_type | purchase | chosen on upload |
| challan_no / invoice_no | INV-ADU/4344/1026 | grouping key; part of the duplicate key |
| doc_date, doc_time | 2026-10-15, 16:51:46 | Mushak 6.3 prints the time |
| supplier_name, supplier_bin | Square Toiletries Ltd., 000241211-1105 | |
| supplier_issue_address | Meril Road Shalgaria, Pabna | |
| supplier_branch_name, branch_address | Square Toiletries Ltd. (Adhuria Plant), Adhuria, Rupgonj, Narayangonj | |
| buyer_name, buyer_bin, buyer_address | M/S Khaza Enterprise - (Kitchen Market) Toi+Soap, 0, Shop No:15 … Dhaka-1215 | buyer = Khaza → purchase |
| delivery_address | same as buyer | |
| vehicle_type, vehicle_no | COVER VAN, DM-NA-20-3036 | links to vehicles master |
| total_qty, total_value, total_sd, total_vat, grand_total | 5188, 463810.63, 4197.39, 70201.20, 538209.2x | control totals |
| authorised_person, designation | Md. Sarwar Hossain, Jr. Executive | optional |
| form_code | Mushak-6.3 | optional |

### Purchase: lines sheet

`invoice_no, serial, product_code (optional), product_description, unit, qty, bonus_qty (default 0), unit_price_ex_tax, line_discount (default 0), value_ex_tax, sd_pct, sd_amount, vat_pct, vat_amount, total_incl_tax, batch_no (optional), expiry (optional), notes`

### Sale (Square Invoice Report): header sheet

| Field | Example | Notes |
|---|---|---|
| invoice_no | 2610050652897 | 13 digits; part of the duplicate key |
| invoice_date, delivery_date | 2026-10-15, 2026-10-16 | |
| invoice_type | Normal | |
| issuer_name, issuer_address | Square Toiletries Ltd., Samson Center … Dhaka-1212 | |
| distributor_name, distributor_code | M/S Khaza Enterprise, 4831 | |
| route_name, route_code | Kitchen Market, 000240 | |
| sr_name, sr_code | Mr. Mir Tanvir, 00007287 | |
| retailer_name (Bangla), retailer_code | মতালিব স্টোর, 366350 | |
| retailer_address, retailer_contact | কিচেন মার্কেট তেজগাঁও ঢাকা, 1711459077 | phone needs normalising to 01… |
| retailer_discount_pct | (blank) | |
| rep_collection | 0.00 | cash collected at delivery → payment |
| subtotal | 21716.86 | sum of invoice lines |
| combo_bonus_subtotal | 0.00 | |
| total_amt | 21716.86 | |
| slab_adjustment | 419.73 | header-level discount |
| combo_bonus_policy_price_disc | 419.7312 | 4 dp; informational, or the source of the slab adjustment |
| rounding_adjustment | −0.13 | derived or entered |
| net_total_amt | 21297.00 | receivable amount |
| amount_in_words | … | optional, not imported |

### Sale: lines sheet

`invoice_no, section (Invoice | Invoice Combo Bonus), line_type (invoice | bonus | combo_bonus), serial, product_code (optional), product_name (Bangla or English), pack_size, unit_price, qty, prod_discount, bonus_qty, amount, parent_serial (for bonus rows), notes`

### Flat sheet (one row per line)

All header fields repeated on each line, plus the line fields above, with a `doc_type` column. Grouped by `invoice_no` (+ party + date).

### Parsing rules the samples imply

- Numbers come with thousands separators ("1,440", "34,309.44") and percentages as "15%" or "0%". Strip both.
- The Amount column can contain the text "Bonus". Treat it as amount 0 with line_type = bonus.
- Bonus rows have no serial number; attach them to the previous numbered line.
- Section heading rows ("Invoice", "Invoice Combo Bonus") and "Sub Total" rows are not lines.
- Dates arrive as `dd/mm/yyyy` (challan) and `dd-mm-yyyy` (invoice).
- Bangla digits (১৫৬, ৩০০) appear inside product names. Normalise them for matching.

## 3. Arithmetic checks

### Purchase challan (17 lines)

| Check | Result |
|---|---|
| qty × printed unit_price = value | 0 exact matches. Every line passes with tolerance qty × 0.005. Largest gaps: line 1 −5.76 (1440 qty), line 7 +4.06 (1040 qty) |
| SD = value × SD% (2 dp) | 17/17 pass |
| VAT = (value + SD) × 15% (2 dp) | 17/17 pass |
| value + SD + VAT = printed total | 10/16 exact (line 17 total unreadable). Lines 6, 7, 9 are −0.01 and lines 8, 12, 15 are +0.01, because the total is computed from unrounded figures |
| Σ qty = 5,188 | pass |
| Σ value = 463,810.63 | pass |
| Σ SD = 4,197.39 | pass (470.46 + 1,019.33 + 2,337.39 + 370.21) |
| Σ VAT = 70,201.20 printed | **−0.01 mismatch**: lines sum to 70,201.21 |
| Σ total incl. tax | printed value clipped ("538,209.??"). Sum of line totals = 538,209.23; value + SD + printed VAT = 538,209.22 |

### Sales invoice report

| Check | Result |
|---|---|
| qty × price − prod_discount = amount | 9/9 exact |
| Σ invoice lines = Sub Total 21,716.86 | pass |
| Combo bonus lines amount = 0, Sub Total 0.00 | pass |
| Total Amt = Sub Total | pass (21,716.86) |
| Total − Slab Adj = Net Total | 21,716.86 − 419.73 = 21,297.13 vs printed 21,297.00. **−0.13 difference, explained as rounding to whole taka** (amount in words confirms 21,297) |
| Combo Bonus Policy 419.7312 | equals 4% × 10,493.28 (lines 2+3+4) exactly; rounds to the Slab Adjustment of 419.73 |
| Free goods | 42 pcs; value at printed unit prices = 3×107.00 + 24×26.80 + 4×57.81 + 11×57.60 = 1,829.04 (zero revenue under rule 3) |

## 4. Values not readable or inferred (explicit)

| Where | Printed / visible | Value used | Basis |
|---|---|---|---|
| Challan line 12 qty | looks like "90" | **96** | Σ qty 5,188 only works with 96; 17,947.82 ÷ 96 = 186.9565 |
| Challan line 12 unit price | looks like "180.96" | **186.96** | as above |
| Challan line 12 VAT / total | "2,092.17" / "20,040.00" glyphs | **2,692.17 / 20,640.00** | 15% of 17,947.82 = 2,692.17; TP 215.00 × 96 |
| Challan line 7 value | last digit 5 or 6 | 56,340.86 | VAT 8,451.13 works with either; Σ value 463,810.63 confirms .86 |
| Challan line 17 total | "47,999.5?" clipped | **left blank in CSV**; computed 47,999.53 | not printed-confirmed |
| Challan grand total | "538,209.??" clipped | null; computed 538,209.22 or .23 | |
| Challan line 5 description | "…dispenser)-1(" + "gm" | "-10 gm" | clipped at the cell edge |
| Challan number | "INV-ADUI4344I1026" | INV-ADU/4344/1026 | "I" read as "/" |
| Challan footer central BIN | "000241211-110?" | assumed same as seller BIN | clipped |
| Challan Bangla column headers | small and blurred | reconstructed from the standard 6.3 layout | |
| Invoice no | top of digits clipped | 2610050652897 | barcode not decoded |
| Invoice and delivery year | "202?" clipped | 2026 | inferred from the challan date |
| SR code | "(0000/287)" | 00007287 | "/" read as "7" |
| Slab Adjustment | "41?.73" behind a fold | 419.73 | matches combo policy 419.7312 |
| Net Total | faded "21,297.00" | 21,297.00 | amount in words |
| Retailer name | মতালব / মতালিব স্টোর | মতালিব স্টোর | Bangla font rendering |
| Invoice line 1 name | "মেরিল বোব লোশন" | মেরিল বেবি লোশন (or বডি) | low confidence |
| Invoice line 2 name | "…(৩৯ পিসের জা" | জার inferred | truncated |
| Invoice line 3 and combo line 1 | "(১৫৬ পিসের ?" / "(১২৬…" | ১৫৬, last word unreadable | truncated and blurred |
| Invoice line 6 name | "…১০০ গ্রাম এ" | suffix truncated | |
| Invoice line 7 flavour | "কি৬ৎ"-like glyphs | কিউই (kiwi) guess | low confidence |
| "এনাপ" suffix (lines 6–8, combo 3) | printed | kept as printed | meaning unknown |
| Combo line 2 pack | "(২৬ পিসের জা[র]" | kept | differs from invoice line 4 "৫২ পিস" at the same price 57.81 |

## 5. Open questions for the coordinator / owner

1. Should stock cost include SD (proposed: yes, because SD is not recoverable)?
2. Should sales revenue be VAT-inclusive TP (as printed), given that purchase cost is ex-VAT?
3. Should Slab Adjustment / Combo Bonus Policy be modelled as a header-level discount that reduces revenue (proposed), and should whole-taka rounding be modelled as a separate rounding line?
4. Should petroleum jelly 50 ml at TP 62.30 (challan) and "৫২ পিস 50 ml" at 57.81 (invoice) be one SKU or two? The owner needs to confirm this, along with the S02/S03 15 ml variants.
5. Can the owner provide Square product codes (the master software export) so that matching does not rely on names?
