<?php
// --- STAŽENÍ AKTUÁLNÍHO KURZU ČNB (EUR) ---
$eur_rate = 25.00;
try {
    $ctx = stream_context_create(['http' => ['timeout' => 2]]);
    $cnb_data = @file_get_contents('https://www.cnb.cz/cs/financni-trhy/devizovy-trh/kurzy-devizoveho-trhu/kurzy-devizoveho-trhu/denni_kurz.txt', false, $ctx);
    if ($cnb_data) {
        $lines = explode("\n", $cnb_data);
        foreach ($lines as $line) {
            if (strpos($line, '|EUR|') !== false) {
                $parts = explode('|', $line);
                $eur_rate = (float)str_replace(',', '.', trim($parts[4]));
                break;
            }
        }
    }
} catch (Exception $e) {}
?>
<script>
    var CNB_EUR_RATE = <?= number_format($eur_rate, 3, '.', '') ?>;
</script>

<div class="modal fade" id="mWF" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div class="modal-header" style="color:#fff; background:#337ab7; border-radius: 12px 12px 0 0; padding: 15px 20px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:1;">&times;</button>
                <h4 class="modal-title" style="font-weight: bold;">Správa položky</h4>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <div id="mWFSarzeBox" style="display:none; background: #fff4e5; padding: 15px; border: 1px solid #f0ad4e; border-radius: 8px; margin-bottom: 15px;">
                    <label class="small text-danger" style="text-transform: uppercase; font-weight: bold;">Šarže dodavatele (Povinné k analýze)</label>
                    <input type="text" id="mWFSarze" class="form-control" placeholder="Zadejte šarži...">
                </div>

                <div id="mWFUploadBox" class="panel-group" style="margin-bottom: 15px;">
                    <div class="panel panel-default" style="border-radius: 8px; margin-bottom: 5px;">
                        <div class="panel-heading" style="display:flex; justify-content:space-between; align-items:center; padding: 8px 12px; background: #fcfcfc;">
                            <h5 style="margin:0; font-size:13px; font-weight: bold;"><i class="glyphicon glyphicon-list-alt text-primary"></i> TDS (Specifikace)</h5>
                            <button type="button" class="btn btn-xs btn-success btn-toggle-upload" data-target="TDS" style="border-radius: 4px;"><i class="glyphicon glyphicon-plus"></i></button>
                        </div>
                        <div class="panel-body" style="padding:10px;">
                            <div id="listTDS"></div>
                            <div id="uploadTDS" style="display:none; margin-top:10px;">
                                <input type="file" id="mWFFileSpec" multiple class="form-control input-sm">
                            </div>
                        </div>
                    </div>
                    <div class="panel panel-default" style="border-radius: 8px; margin-bottom: 5px;">
                        <div class="panel-heading" style="display:flex; justify-content:space-between; align-items:center; padding: 8px 12px; background: #fcfcfc;">
                            <h5 style="margin:0; font-size:13px; font-weight: bold;"><i class="glyphicon glyphicon-tint text-danger"></i> COA (Analýza / Lab)</h5>
                            <button type="button" class="btn btn-xs btn-success btn-toggle-upload" data-target="COA" style="border-radius: 4px;"><i class="glyphicon glyphicon-plus"></i></button>
                        </div>
                        <div class="panel-body" style="padding:10px;">
                            <div id="listCOA"></div>
                            <div id="uploadCOA" style="display:none; margin-top:10px;">
                                <input type="file" id="mWFFileLab" multiple class="form-control input-sm">
                            </div>
                        </div>
                    </div>
                    <div class="panel panel-default" style="border-radius: 8px;">
                        <div class="panel-heading" style="display:flex; justify-content:space-between; align-items:center; padding: 8px 12px; background: #fcfcfc;">
                            <h5 style="margin:0; font-size:13px; font-weight: bold;"><i class="glyphicon glyphicon-file text-muted"></i> Ostatní přílohy</h5>
                            <button type="button" class="btn btn-xs btn-success btn-toggle-upload" data-target="Other" style="border-radius: 4px;"><i class="glyphicon glyphicon-plus"></i></button>
                        </div>
                        <div class="panel-body" style="padding:10px;">
                            <div id="listOther"></div>
                            <div id="uploadOther" style="display:none; margin-top:10px;">
                                <input type="file" id="mWFFileOther" multiple class="form-control input-sm">
                            </div>
                        </div>
                    </div>
                </div>

                <div id="mWFQtyBox" style="display:none; margin-bottom: 15px;">
                    <label class="small text-muted" style="text-transform: uppercase;">Množství k objednání</label>
                    <input type="text" id="mWFQty" class="form-control" placeholder="např. 500 kg">
                </div>

                <div id="mWFItemCodeBox" style="display:none; border: 1px solid #28a745; padding: 15px; border-radius: 8px; background: #f8fff8; margin-bottom: 15px;">
                    <label class="small text-success" style="text-transform: uppercase; font-weight: bold;">Zápis do Informačního systému</label>
                    <div class="row">
                        <div class="col-xs-6"><input type="text" id="mWFSkupZbo" class="form-control" placeholder="skupzbo"></div>
                        <div class="col-xs-6"><input type="text" id="mWFRegCis" class="form-control" placeholder="regcis"></div>
                    </div>
                </div>

                <div id="mWFFileStatus"></div>
                <button type="button" class="btn btn-success btn-block" id="btnDoUpload" style="display:none; font-weight: bold; margin-bottom: 15px;">
                    <i class="glyphicon glyphicon-upload"></i> Nahrát vybrané soubory
                </button>

                <div class="form-group">
                    <label class="small text-muted" style="text-transform: uppercase;">Poznámka</label>
                    <textarea id="mWFNote" class="form-control" rows="2" style="border-radius: 8px; resize: none;"></textarea>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #f5f5f5; padding: 15px 20px;">
                <button type="button" class="btn btn-primary btn-block btn-lg" id="mWFSave" style="border-radius: 8px; font-weight: bold;">POTVRDIT A ZAVŘÍT</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mReason" tabindex="-1" style="z-index: 9999;">
    <div class="modal-dialog modal-sm">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header" style="background:#d9534f; color:#fff; border-radius: 12px 12px 0 0; padding: 12px 15px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:1;">&times;</button>
                <h4 class="modal-title" style="font-weight: bold;">Zadejte důvod</h4>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <input type="hidden" id="mReasonId">
                <input type="hidden" id="mReasonStatus">
                <div class="form-group">
                    <label class="small text-muted" style="text-transform: uppercase;">Vysvětlení pro kolegy</label>
                    <textarea id="mReasonText" class="form-control" rows="3" placeholder="Proč je to KO nebo co chybí?" style="border-radius: 8px; resize: none;"></textarea>
                </div>
            </div>
            <div class="modal-footer" style="padding: 15px; border-top: 1px solid #f5f5f5;">
                <button type="button" class="btn btn-danger btn-block" id="mReasonSave" style="border-radius: 8px; font-weight: bold; height: 40px;">POTVRDIT AKCI</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mNN" tabindex="-1">
    <div class="modal-dialog" style="width: 420px;">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 15px 35px rgba(0,0,0,0.25);">
            <div class="modal-header" style="background:#337ab7; color:#fff; border-radius: 12px 12px 0 0; padding: 15px 20px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:1;">&times;</button>
                <h4 class="modal-title" style="font-weight: bold;"><i class="glyphicon glyphicon-plus"></i> Cenová nabídka</h4>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <input type="hidden" id="mNNId">

                <div class="form-group">
                    <label class="small text-muted" style="text-transform: uppercase;">Dodavatel</label>
                    <select id="mNNDod" class="form-control select2-dod" style="width:100%;">
                        <option value="">-- Vyberte nebo napište --</option>
                        <?php
                        $d_res = mysqli_query($conn, "SELECT nazev FROM dodavatele ORDER BY nazev ASC");
                        while($d = mysqli_fetch_assoc($d_res)) echo "<option value='".htmlspecialchars($d['nazev'])."'>".htmlspecialchars($d['nazev'])."</option>";
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="small text-muted" style="text-transform: uppercase;">Cena za MJ (bez DPH)</label>
                    <div class="input-group">
                        <input type="number" id="mNNCena" class="form-control input-lg" step="0.01" placeholder="Zadejte cenu..." style="border-radius: 8px 0 0 8px; border-right: none; box-shadow: inset 0 1px 1px rgba(0,0,0,.05);">
                        <span class="input-group-addon" style="padding: 0; border: none; border-radius: 0 8px 8px 0; background: transparent;">
                            <select id="mNNMena" class="form-control input-lg" style="border-radius: 0 8px 8px 0; border-left: 1px solid #ddd; background: #f0f7ff; color: #337ab7; font-weight: bold; width: 90px; box-shadow: none;">
                                <option value="CZK">CZK</option>
                                <option value="EUR">EUR</option>
                            </select>
                        </span>
                    </div>
                    <div id="mNNKurzInfo" style="display:none; font-size: 11px; color: #337ab7; margin-top: 4px; font-weight: bold; padding-left: 2px;">
                        <i class="glyphicon glyphicon-info-sign"></i> Přepočet: <span id="mNNCzkCalc">0.00</span> CZK (Kurz ČNB: <?= number_format($eur_rate, 3, ',', ' ') ?> Kč)
                    </div>
                </div>

                <div id="mNNDopravaWrapper" style="display: none; background: #fcfcfc; padding: 15px; border-radius: 8px; border: 1px dashed #ced4da; margin-top: 15px; margin-bottom: 15px;">
                    <label class="small text-muted" style="text-transform: uppercase;"><i class="glyphicon glyphicon-road"></i> Dopravné na suroviny</label>
                    <select id="mNNDoprava" class="form-control" style="border-radius: 8px; font-weight: bold; color: #444;">
                        <option value="">-- Zvolte kategorii dopravného --</option>
                        <option value="10">Kategorie A (+ 10 Kč / MJ)</option>
                        <option value="15">Kategorie B (+ 15 Kč / MJ)</option>
                        <option value="20">Kategorie C (+ 20 Kč / MJ)</option>
                        <option value="25">Kategorie D (+ 25 Kč / MJ)</option>
                        <option value="custom">Kategorie E (Volitelná částka)</option>
                    </select>
                    <div id="mNNDopravaCustomWrapper" style="display:none; margin-top: 10px;">
                        <div class="input-group">
                            <input type="number" id="mNNDopravaCustom" class="form-control" placeholder="Zadejte vlastní částku..." step="0.1">
                            <span class="input-group-addon" style="border-radius: 0 8px 8px 0; background: #eee;">Kč / MJ</span>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; align-items: flex-end; margin-top: 15px;">
                    <div style="flex-grow: 1;">
                        <label class="small text-muted" style="text-transform: uppercase;">MOQ (Minimální odběr)</label>
                        <input type="number" id="mNNMoqQty" class="form-control" step="0.1" placeholder="Nepovinné" style="border-radius: 8px;">
                    </div>
                    <div id="mNNMoqMjWrapper" style="width: 80px; display: none;">
                        <label class="small text-muted" style="text-transform: uppercase;">Jedn.</label>
                        <select id="mNNMoqMj" class="form-control" style="border-radius: 8px; background: #f0f7ff; border-color: #337ab7;">
                            <option value="kg" selected>kg</option>
                            <option value="l">l</option>
                            <option value="ks">ks</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label class="small text-muted" style="text-transform: uppercase;">Interní poznámka</label>
                    <textarea id="mNNPozn" class="form-control" rows="2" placeholder="Doprava, platnost, balení..." style="border-radius: 8px; resize: none;"></textarea>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #f5f5f5; padding: 15px 20px;">
                <button type="button" class="btn btn-primary btn-block btn-lg" id="mNNSave" style="font-weight:bold; border-radius: 8px;">ULOŽIT NABÍDKU</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mQtyNote" tabindex="-1" style="z-index: 9999;">
    <div class="modal-dialog modal-sm">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header" style="background:#28a745; color:#fff; border-radius: 12px 12px 0 0; padding: 12px 15px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:1;">&times;</button>
                <h4 class="modal-title" style="font-weight: bold;">Schválit k testování</h4>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <input type="hidden" id="mQNId">
                <input type="hidden" id="mQNStatus">

                <div class="form-group">
                    <label class="small text-muted" style="text-transform: uppercase;">Požadované množství vzorku ⚖️</label>
                    <input type="text" id="mQNQty" class="form-control" placeholder="např. 1 kg nebo 500 ml" style="border-radius: 8px;">
                </div>

                <div class="form-group">
                    <label class="small text-muted" style="text-transform: uppercase;">Instrukce pro nákup ✉️</label>
                    <textarea id="mQNNote" class="form-control" rows="3" placeholder="Poznámka k objednávce vzorku..." style="border-radius: 8px; resize: none;"></textarea>
                </div>
            </div>
            <div class="modal-footer" style="padding: 15px; border-top: 1px solid #f5f5f5;">
                <button type="button" class="btn btn-success btn-block" id="mQNSave" style="border-radius: 8px; font-weight: bold; height: 40px;">POTVRDIT SCHVÁLENÍ</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mEditReq" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header" style="background:#f0ad4e; color:#fff; border-radius: 12px 12px 0 0; padding: 15px 20px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:1;">&times;</button>
                <h4 class="modal-title" style="font-weight: bold;">Upravit požadavek: <span id="mEditReqTitle"></span></h4>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <input type="hidden" id="mEditReqId">

                <div class="form-group" style="background:#fcfcfc; padding:15px; border:1px solid #eee; border-radius:10px;">
                    <label class="small text-muted" style="display:block; margin-bottom:10px; text-transform: uppercase;">Požadované parametry</label>
                    <label class="checkbox-inline" style="font-weight: bold;"><input type="checkbox" id="mEditReqBio"> BIO</label>
                    <label class="checkbox-inline" style="font-weight: bold;"><input type="checkbox" id="mEditReqVegan"> Vegan</label>
                    <label class="checkbox-inline" style="font-weight: bold;"><input type="checkbox" id="mEditReqBezlepek"> Bezlepek</label>
                    <label class="checkbox-inline" style="font-weight: bold;"><input type="checkbox" id="mEditReqKosher"> Kosher</label>
                    <label class="checkbox-inline" style="font-weight: bold;"><input type="checkbox" id="mEditReqHalal"> Halal</label>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label class="small text-muted" style="text-transform: uppercase;">Priorita požadavku</label>
                    <select id="mEditReqPrio" class="form-control" style="border-radius: 8px;">
                        <option value="0">Normální</option>
                        <option value="1">Urgentní</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="small text-muted" style="text-transform: uppercase;">Poznámka k zadání</label>
                    <textarea id="mEditReqNote" class="form-control" rows="3" style="border-radius: 8px; resize: none;"></textarea>
                </div>
            </div>
            <div class="modal-footer" style="padding: 15px; border-top: 1px solid #f5f5f5; display: flex; justify-content: space-between; gap: 10px;">
                <button type="button" class="btn btn-danger btn-lg" id="btnOpenCancelReq" style="border-radius: 8px; font-weight:bold; flex: 1;">❌ ZRUŠIT</button>
                <button type="button" class="btn btn-warning btn-lg" id="mEditReqSave" style="border-radius: 8px; font-weight:bold; color: #fff; flex: 2;">ULOŽIT ZMĚNY</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mCancelReqModal" tabindex="-1" style="z-index: 10000;">
    <div class="modal-dialog modal-sm">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(217, 83, 79, 0.4);">
            <div class="modal-header" style="background:#d9534f; color:#fff; border-radius: 12px 12px 0 0; padding: 12px 15px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:1;">&times;</button>
                <h4 class="modal-title" style="font-weight: bold;"><i class="glyphicon glyphicon-trash"></i> Zrušit požadavek</h4>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <input type="hidden" id="mCancelReqId">
                <div class="form-group">
                    <label class="small text-muted" style="text-transform: uppercase;">Důvod zrušení (Povinné)</label>
                    <textarea id="mCancelReqReason" class="form-control" rows="3" placeholder="Např. omyl, duplicita, již nepotřebujeme..." style="border-radius: 8px; resize: none;"></textarea>
                </div>
            </div>
            <div class="modal-footer" style="padding: 15px; border-top: 1px solid #f5f5f5;">
                <button type="button" class="btn btn-danger btn-block" id="mCancelReqSave" style="border-radius: 8px; font-weight: bold; height: 40px;">POTVRDIT ZRUŠENÍ</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="mFullComments" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: #f8f9fa;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" style="font-size: 18px; font-weight: bold;"><i class="glyphicon glyphicon-comment"></i> Celá historie poznámek</h4>
            </div>
            <div class="modal-body" id="mFullCommentsBody" style="max-height: 70vh; overflow-y: auto;">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">Zavřít</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="mReqDetail" tabindex="-1">
    <div class="modal-dialog" style="width: 95%; max-width: 1400px;">
        <div class="modal-content" style="border-radius: 8px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
            <div class="modal-header" style="background:#2c3e50; color:#fff; border-radius: 8px 8px 0 0; padding: 15px 20px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity: 1;">&times;</button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-list-alt"></i> Detail požadavku</h4>
            </div>
            <div class="modal-body" id="mReqDetailContent" style="padding: 0; background: #f4f6f9;">
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="mOfferFiles" tabindex="-1" style="z-index: 9999;">
    <div class="modal-dialog modal-sm">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div class="modal-header" style="background:#f4f6f9; border-radius: 12px 12px 0 0; padding: 15px 20px; border-bottom: 1px solid #ddd;">
                <button type="button" class="close" data-dismiss="modal" style="opacity:1;">&times;</button>
                <h4 class="modal-title" style="font-weight: bold; color: #333;"><i class="glyphicon glyphicon-paperclip"></i> Přiložené soubory</h4>
            </div>
            <div class="modal-body" id="mOfferFilesContent" style="padding: 20px;">
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="mQualityModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #5cb85c; color: white;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-ok"></i> Schválit COA</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="mQualId">
                <input type="hidden" id="mQualStatus">
                <div class="form-group">
                    <label>Zadejte číslo Šarže (LOT) z dokumentu:</label>
                    <input type="text" class="form-control" id="mQualSarze" placeholder="Např. LOT123456">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Zrušit</button>
                <button type="button" class="btn btn-success" id="mQualSave">Potvrdit schválení</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="mDuplicateWarning" tabindex="-1" role="dialog" style="z-index: 1060;"> <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #f0ad4e; color: white;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-warning-sign"></i> Pozor: Duplicita</h4>
            </div>
            <div class="modal-body">
                <p>Na tuto surovinu již aktivně běží výběrové řízení <strong>(Požadavek #<span id="dupExistId"></span>)</strong>.</p>
                <p>Chcete i přesto založit ÚPLNĚ NOVÝ požadavek (např. pro jiný projekt / duální nákup)?</p>
            </div>
            <div class="modal-footer" style="text-align: center;">
                <button type="button" class="btn btn-default btn-block" id="btnDupGoTo">Zrušit a přejít na existující</button>
                <button type="button" class="btn btn-warning btn-block" id="btnDupForce" style="margin-top: 5px;">Ano, založit nový</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="mPingPurchasing" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #5bc0de; color: white;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-bell"></i> Vyžádat nabídku</h4>
            </div>
            <div class="modal-body">
                <p>Odeslat Nákupu upozornění, že potřebujete vyhledat další alternativu/nabídku pro surovinu <strong><span id="pingSurName"></span></strong>?</p>
                <input type="hidden" id="pingReqId">
                <input type="hidden" id="pingSurRaw">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Zrušit</button>
                <button type="button" class="btn btn-info" id="btnConfirmPing">Ano, odeslat</button>
            </div>
        </div>
    </div>
</div>
<script>
    $(document).ready(function() {
        // Kliknutí na modrý (TDS) nebo červený (COA) štítek na malé kartičce
        $(document).on('click', '.btn-open-files-modal', function(e) {
            e.preventDefault();
            var offerId = $(this).data('id');

            // Zobrazíme "načítací" text
            $('#mOfferFilesContent').html('<div style="text-align:center; color:#999; padding: 20px;"><i class="glyphicon glyphicon-refresh spinning"></i> Načítám seznam...</div>');

            // Otevřeme modál
            $('#mOfferFiles').modal('show');

            // Zavoláme náš nový skript pro získání odkazů
            $.post('includes/ajax_offer_files_modal.php', { id: offerId }, function(data) {
                $('#mOfferFilesContent').html(data);
            }).fail(function() {
                $('#mOfferFilesContent').html('<div class="alert alert-danger">Chyba při načítání souborů ze serveru.</div>');
            });
        });
    });
</script>