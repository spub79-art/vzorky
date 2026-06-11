<?php
// --- CHYTRÉ STAŽENÍ KURZU ČNB S CACHE (jen 1x denně) ---
$eur_rate = 25.10;
$usd_rate = 23.50; // Fallback
$cache_file = __DIR__ . '/cnb_cache.json';
$today = date('Y-m-d');
$need_fetch = true;

if (file_exists($cache_file)) {
    $cache_data = json_decode(file_get_contents($cache_file), true);
    if ($cache_data && isset($cache_data['date']) && $cache_data['date'] === $today) {
        $eur_rate = (float)$cache_data['eur'];
        $usd_rate = (float)$cache_data['usd'];
        $need_fetch = false;
    }
}

if ($need_fetch) {
    try {
        $ctx = stream_context_create(['http' => ['timeout' => 2]]);
        $cnb_data = @file_get_contents('https://www.cnb.cz/cs/financni-trhy/devizovy-trh/kurzy-devizoveho-trhu/kurzy-devizoveho-trhu/denni_kurz.txt', false, $ctx);
        if ($cnb_data) {
            $lines = explode("\n", $cnb_data);
            foreach ($lines as $line) {
                if (strpos($line, '|EUR|') !== false) {
                    $parts = explode('|', $line);
                    $eur_rate = (float)str_replace(',', '.', trim($parts[4]));
                }
                if (strpos($line, '|USD|') !== false) {
                    $parts = explode('|', $line);
                    $usd_rate = (float)str_replace(',', '.', trim($parts[4]));
                }
            }
            @file_put_contents($cache_file, json_encode(['date' => $today, 'eur' => $eur_rate, 'usd' => $usd_rate]));
        }
    } catch (Exception $e) {}
}
?>
<script>
    window.CNB_EUR_RATE = <?= number_format($eur_rate, 3, '.', '') ?>;
    window.CNB_USD_RATE = <?= number_format($usd_rate, 3, '.', '') ?>;
</script>

<div class="modal fade" id="mWF" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content modal-modern">
            <div class="modal-header modal-header-modern header-primary">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Správa položky</h4>
            </div>
            <div class="modal-body modal-body-modern">
                <div id="mWFSarzeBox" class="box-modern box-warning" style="display:none;">
                    <label class="small text-danger label-uppercase">Šarže dodavatele (Povinné k analýze)</label>
                    <input type="text" id="mWFSarze" class="form-control" placeholder="Zadejte šarži...">
                </div>

                <div id="mWFUploadBox" class="panel-group" style="margin-bottom: 15px;">
                    <div class="panel panel-modern">
                        <div class="panel-heading-modern">
                            <h5 class="panel-title-modern"><i class="glyphicon glyphicon-list-alt text-primary"></i> TDS (Specifikace)</h5>
                            <button type="button" class="btn btn-xs btn-success btn-xs-modern btn-toggle-upload" data-target="TDS"><i class="glyphicon glyphicon-plus"></i></button>
                        </div>
                        <div class="panel-body panel-body-modern">
                            <div id="listTDS"></div>
                            <div id="uploadTDS" style="display:none; margin-top:10px;">
                                <input type="file" id="mWFFileSpec" multiple class="form-control input-sm">
                            </div>
                        </div>
                    </div>
                    <div class="panel panel-modern">
                        <div class="panel-heading-modern">
                            <h5 class="panel-title-modern"><i class="glyphicon glyphicon-tint text-danger"></i> COA (Analýza / Lab)</h5>
                            <button type="button" class="btn btn-xs btn-success btn-xs-modern btn-toggle-upload" data-target="COA"><i class="glyphicon glyphicon-plus"></i></button>
                        </div>
                        <div class="panel-body panel-body-modern">
                            <div id="listCOA"></div>
                            <div id="uploadCOA" style="display:none; margin-top:10px;">
                                <input type="file" id="mWFFileLab" multiple class="form-control input-sm">
                            </div>
                        </div>
                    </div>
                    <div class="panel panel-modern">
                        <div class="panel-heading-modern">
                            <h5 class="panel-title-modern"><i class="glyphicon glyphicon-file text-muted"></i> Ostatní přílohy</h5>
                            <button type="button" class="btn btn-xs btn-success btn-xs-modern btn-toggle-upload" data-target="Other"><i class="glyphicon glyphicon-plus"></i></button>
                        </div>
                        <div class="panel-body panel-body-modern">
                            <div id="listOther"></div>
                            <div id="uploadOther" style="display:none; margin-top:10px;">
                                <input type="file" id="mWFFileOther" multiple class="form-control input-sm">
                            </div>
                        </div>
                    </div>
                </div>

                <div id="mWFQtyBox" class="form-group" style="display:none;">
                    <label class="small text-muted label-uppercase">Množství k objednání</label>
                    <input type="text" id="mWFQty" class="form-control" placeholder="např. 500 kg">
                </div>

                <div id="mWFItemCodeBox" class="box-modern box-success" style="display:none;">
                    <label class="small text-success label-uppercase">Zápis do Informačního systému</label>
                    <div class="row">
                        <div class="col-xs-6"><input type="text" id="mWFSkupZbo" class="form-control" placeholder="skupzbo"></div>
                        <div class="col-xs-6"><input type="text" id="mWFRegCis" class="form-control" placeholder="regcis"></div>
                    </div>
                </div>

                <div id="mWFFileStatus"></div>
                <button type="button" class="btn btn-success btn-block btn-modern" id="btnDoUpload" style="display:none; margin-bottom: 15px;">
                    <i class="glyphicon glyphicon-upload"></i> Nahrát vybrané soubory
                </button>

                <div class="form-group">
                    <label class="small text-muted label-uppercase">Poznámka</label>
                    <textarea id="mWFNote" class="form-control textarea-modern" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer modal-footer-modern">
                <button type="button" class="btn btn-primary btn-block btn-lg btn-modern" id="mWFSave">POTVRDIT A ZAVŘÍT</button>
            </div>
        </div>
    </div>
</div>

<div id="mExportModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content modal-modern">
            <div class="modal-header modal-header-modern header-primary">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-list-alt"></i> Generátor: Co aktuálně sháníme</h4>
            </div>
            <div class="modal-body modal-body-modern box-light">
                <div id="mExportModalBody">
                    <div class="text-center text-muted" style="padding: 40px;">
                        <i class="glyphicon glyphicon-refresh spinning" style="font-size: 30px;"></i><br><br>
                        Načítám seznam z Fáze 1...
                    </div>
                </div>
            </div>
            <div class="modal-footer modal-footer-modern">
                <button type="button" class="btn btn-default" data-dismiss="modal">Zavřít</button>
                <button type="button" class="btn btn-success" id="btnCopyExport"><i class="glyphicon glyphicon-copy"></i> Kopírovat text do schránky</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mReason" tabindex="-1" style="z-index: 9999;">
    <div class="modal-dialog modal-sm">
        <div class="modal-content modal-modern">
            <div class="modal-header modal-header-modern header-danger">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Zadejte důvod</h4>
            </div>
            <div class="modal-body modal-body-modern">
                <input type="hidden" id="mReasonId">
                <input type="hidden" id="mReasonStatus">
                <div class="form-group">
                    <label class="small text-muted label-uppercase">Vysvětlení pro kolegy</label>
                    <textarea id="mReasonText" class="form-control textarea-modern" rows="3" placeholder="Proč je to KO nebo co chybí?"></textarea>
                </div>
            </div>
            <div class="modal-footer modal-footer-modern">
                <button type="button" class="btn btn-danger btn-block btn-modern btn-modern-tall" id="mReasonSave">POTVRDIT AKCI</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mNN" tabindex="-1">
    <div class="modal-dialog modal-dialog-420">
        <div class="modal-content modal-modern shadow-heavy">
            <div class="modal-header modal-header-modern header-primary">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-plus"></i> Cenová nabídka</h4>
            </div>
            <div class="modal-body modal-body-modern">
                <input type="hidden" id="mNNId">

                <div class="form-group">
                    <label class="small text-muted label-uppercase">Dodavatel</label>
                    <select id="mNNDod" class="form-control select2-dod" style="width:100%;">
                        <option value="">-- Vyberte nebo napište --</option>
                        <?php
                        $d_res = mysqli_query($conn, "SELECT nazev FROM dodavatele ORDER BY nazev ASC");
                        while($d = mysqli_fetch_assoc($d_res)) echo "<option value='".htmlspecialchars($d['nazev'])."'>".htmlspecialchars($d['nazev'])."</option>";
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="small text-muted label-uppercase">Cena za MJ (bez DPH)</label>
                    <div class="input-group">
                        <input type="number" id="mNNCena" class="form-control input-lg input-modern" step="0.01" placeholder="Zadejte cenu..." style="border-right: none; box-shadow: inset 0 1px 1px rgba(0,0,0,.05);">
                        <span class="input-group-addon input-group-addon-transparent">
                            <select id="mNNMena" class="form-control input-lg select-currency-modern">
                                <option value="CZK">CZK</option>
                                <option value="EUR">EUR</option>
                                <option value="USD">USD</option>
                            </select>
                        </span>
                    </div>
                    <div id="mNNKurzInfo" style="display:none; font-size: 11px; color: #337ab7; margin-top: 4px; font-weight: bold; padding-left: 2px;">
                        <i class="glyphicon glyphicon-info-sign"></i> Přepočet: <span id="mNNCzkCalc">0.00</span> CZK (Kurz ČNB: <?= number_format($eur_rate, 3, ',', ' ') ?> Kč)
                    </div>
                </div>

                <div id="mNNDopravaWrapper" class="box-modern box-dashed" style="display: none;">
                    <label class="small text-muted label-uppercase"><i class="glyphicon glyphicon-road"></i> Dopravné na suroviny</label>
                    <select id="mNNDoprava" class="form-control input-modern" style="font-weight: bold; color: #444;">
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
                            <span class="input-group-addon input-group-addon-modern">Kč / MJ</span>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; align-items: flex-end; margin-top: 15px;">
                    <div style="flex-grow: 1;">
                        <label class="small text-muted label-uppercase">MOQ (Minimální odběr)</label>
                        <input type="number" id="mNNMoqQty" class="form-control input-modern" step="0.1" placeholder="Nepovinné">
                    </div>
                    <div id="mNNMoqMjWrapper" style="width: 80px; display: none;">
                        <label class="small text-muted label-uppercase">Jedn.</label>
                        <select id="mNNMoqMj" class="form-control input-modern" style="background: #f0f7ff; border-color: #337ab7;">
                            <option value="kg" selected>kg</option>
                            <option value="l">l</option>
                            <option value="ks">ks</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label class="small text-muted label-uppercase">Interní poznámka</label>
                    <textarea id="mNNPozn" class="form-control textarea-modern" rows="2" placeholder="Doprava, platnost, balení..."></textarea>
                </div>
            </div>
            <div class="modal-footer modal-footer-modern">
                <button type="button" class="btn btn-primary btn-block btn-lg btn-modern" id="mNNSave">ULOŽIT NABÍDKU</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mQtyNote" tabindex="-1" style="z-index: 9999;">
    <div class="modal-dialog modal-sm">
        <div class="modal-content modal-modern">
            <div class="modal-header modal-header-modern header-success-dark">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Schválit k testování</h4>
            </div>
            <div class="modal-body modal-body-modern">
                <input type="hidden" id="mQNId">
                <input type="hidden" id="mQNStatus">

                <div class="form-group">
                    <label class="small text-muted label-uppercase">Požadované množství vzorku ⚖️</label>
                    <input type="text" id="mQNQty" class="form-control input-modern" placeholder="např. 1 kg nebo 500 ml">
                </div>

                <div class="form-group">
                    <label class="small text-muted label-uppercase">Instrukce pro nákup ✉️</label>
                    <textarea id="mQNNote" class="form-control textarea-modern" rows="3" placeholder="Poznámka k objednávce vzorku..."></textarea>
                </div>
            </div>
            <div class="modal-footer modal-footer-modern">
                <button type="button" class="btn btn-success btn-block btn-modern btn-modern-tall" id="mQNSave">POTVRDIT SCHVÁLENÍ</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mAddReq" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content modal-modern">
            <div class="modal-header modal-header-modern header-success">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-plus"></i> Nový požadavek</h4>
            </div>
            <div class="modal-body modal-body-modern">

                <div class="form-group">
                    <label>Surovina:</label>
                    <select id="mAddReqSurovina" class="form-control select2-sur" style="width:100%;"></select>
                </div>

                <div class="form-group box-modern box-light">
                    <label class="small text-muted label-uppercase">Požadované parametry</label>
                    <label class="checkbox-inline" style="font-weight: bold;"><input type="checkbox" id="mAddReqBio"> BIO</label>
                    <label class="checkbox-inline" style="font-weight: bold;"><input type="checkbox" id="mAddReqVegan"> Vegan</label>
                    <label class="checkbox-inline" style="font-weight: bold;"><input type="checkbox" id="mAddReqBezlepek"> Bezlepek</label>
                    <label class="checkbox-inline" style="font-weight: bold;"><input type="checkbox" id="mAddReqKosher"> Kosher</label>
                    <label class="checkbox-inline" style="font-weight: bold;"><input type="checkbox" id="mAddReqHalal"> Halal</label>
                </div>

                <div class="form-group">
                    <label class="small text-muted label-uppercase">Priorita požadavku</label>
                    <select id="mAddReqPrio" class="form-control input-modern">
                        <option value="0">Normální</option>
                        <option value="1">Urgentní</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="text-success"><i class="glyphicon glyphicon-user"></i> Zákazník (pro koho je surovina určena):</label>
                    <input type="text" id="mAddReqZakaznik" class="form-control" placeholder="Např. Boon Bar, DM, Lidl... (nepovinné)">
                </div>

                <div class="form-group">
                    <label class="small text-muted label-uppercase">Požadované množství</label>
                    <div class="input-group">
                        <input type="text" id="mAddReqMnozstvi" class="form-control input-modern" placeholder="např. 500">
                        <span class="input-group-addon" style="padding: 0; border: none; background: transparent;">
                            <select id="mAddReqMj" class="form-control input-modern" style="width: 80px; border-radius: 0 4px 4px 0;">
                                <option value="kg">kg</option>
                                <option value="g">g</option>
                                <option value="l">l</option>
                                <option value="ks">ks</option>
                            </select>
                        </span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Poznámka / Zadání:</label>
                    <textarea id="mAddReqNote" class="form-control textarea-modern" rows="4"></textarea>
                </div>
            </div>
            <div class="modal-footer modal-footer-modern">
                <button type="button" class="btn btn-default" data-dismiss="modal">Zrušit</button>
                <button type="button" class="btn btn-success btn-modern" id="mAddReqSave">ZALOŽIT POŽADAVEK</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mEditReq" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content modal-modern">
            <div class="modal-header modal-header-modern header-warning">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Upravit požadavek: <span id="mEditReqTitle"></span></h4>
            </div>
            <div class="modal-body modal-body-modern">
                <input type="hidden" id="mEditReqId">

                <div class="form-group box-modern box-light">
                    <label class="small text-muted label-uppercase">Požadované parametry</label>
                    <label class="checkbox-inline" style="font-weight: bold;"><input type="checkbox" id="mEditReqBio"> BIO</label>
                    <label class="checkbox-inline" style="font-weight: bold;"><input type="checkbox" id="mEditReqVegan"> Vegan</label>
                    <label class="checkbox-inline" style="font-weight: bold;"><input type="checkbox" id="mEditReqBezlepek"> Bezlepek</label>
                    <label class="checkbox-inline" style="font-weight: bold;"><input type="checkbox" id="mEditReqKosher"> Kosher</label>
                    <label class="checkbox-inline" style="font-weight: bold;"><input type="checkbox" id="mEditReqHalal"> Halal</label>
                </div>

                <div class="form-group">
                    <label class="small text-muted label-uppercase">Priorita požadavku</label>
                    <select id="mEditReqPrio" class="form-control input-modern">
                        <option value="0">Normální</option>
                        <option value="1">Urgentní</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="text-warning label-uppercase"><i class="glyphicon glyphicon-user"></i> Zákazníci (pro koho to je):</label>
                    <select id="mEditReqZakaznici" class="form-control" multiple="multiple" style="width:100%;">
                        <?php
                        $z_res_edit = @mysqli_query($conn, "SELECT id, nazev FROM zakaznici ORDER BY nazev ASC");
                        if ($z_res_edit) {
                            while($z = mysqli_fetch_assoc($z_res_edit)) {
                                echo "<option value='".$z['id']."'>".htmlspecialchars($z['nazev'])."</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="small text-muted label-uppercase">Požadované množství</label>
                    <div class="input-group">
                        <input type="text" id="mEditReqMnozstvi" class="form-control input-modern" placeholder="např. 500">
                        <span class="input-group-addon" style="padding: 0; border: none; background: transparent;">
                            <select id="mEditReqMj" class="form-control input-modern" style="width: 80px; border-radius: 0 4px 4px 0;">
                                <option value="kg">kg</option>
                                <option value="g">g</option>
                                <option value="l">l</option>
                                <option value="ks">ks</option>
                            </select>
                        </span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Poznámka / Zadání:</label>
                    <textarea id="mEditReqNote" class="form-control textarea-modern" rows="4"></textarea>
                </div>
            </div>
            <div class="modal-footer modal-footer-modern modal-footer-flex">
                <button type="button" class="btn btn-danger btn-lg btn-modern" id="btnOpenCancelReq" style="flex: 1;">❌ ZRUŠIT</button>
                <button type="button" class="btn btn-warning btn-lg btn-modern" id="mEditReqSave" style="flex: 2;">ULOŽIT ZMĚNY</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mCancelReqModal" tabindex="-1" style="z-index: 10000;">
    <div class="modal-dialog modal-sm">
        <div class="modal-content modal-modern shadow-danger">
            <div class="modal-header modal-header-modern header-danger">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-trash"></i> Zrušit požadavek</h4>
            </div>
            <div class="modal-body modal-body-modern">
                <input type="hidden" id="mCancelReqId">
                <div class="form-group">
                    <label class="small text-muted label-uppercase">Důvod zrušení (Povinné)</label>
                    <textarea id="mCancelReqReason" class="form-control textarea-modern" rows="3" placeholder="Např. omyl, duplicita, již nepotřebujeme..."></textarea>
                </div>
            </div>
            <div class="modal-footer modal-footer-modern">
                <button type="button" class="btn btn-danger btn-block btn-modern btn-modern-tall" id="mCancelReqSave">POTVRDIT ZRUŠENÍ</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mFullComments" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content modal-modern">
            <div class="modal-header modal-header-modern header-light">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-comment"></i> Celá historie poznámek</h4>
            </div>
            <div class="modal-body modal-body-modern" id="mFullCommentsBody" style="max-height: 70vh; overflow-y: auto;">
            </div>
            <div class="modal-footer modal-footer-modern">
                <button type="button" class="btn btn-primary" data-dismiss="modal">Zavřít</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mReqDetail" tabindex="-1">
    <div class="modal-dialog modal-dialog-1400">
        <div class="modal-content modal-modern">
            <div class="modal-header modal-header-modern header-dark">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-list-alt"></i> Detail požadavku</h4>
            </div>
            <div class="modal-body modal-body-gray" id="mReqDetailContent">
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mOfferFiles" tabindex="-1" style="z-index: 9999;">
    <div class="modal-dialog modal-sm">
        <div class="modal-content modal-modern">
            <div class="modal-header modal-header-modern header-light">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-paperclip"></i> Přiložené soubory</h4>
            </div>
            <div class="modal-body modal-body-modern" id="mOfferFilesContent">
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mQualityModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content modal-modern">
            <div class="modal-header modal-header-modern header-success">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-ok"></i> Schválit COA</h4>
            </div>
            <div class="modal-body modal-body-modern">
                <input type="hidden" id="mQualId">
                <input type="hidden" id="mQualStatus">
                <div class="form-group">
                    <label>Zadejte číslo Šarže (LOT) z dokumentu:</label>
                    <input type="text" class="form-control" id="mQualSarze" placeholder="Např. LOT123456">
                </div>
            </div>
            <div class="modal-footer modal-footer-modern">
                <button type="button" class="btn btn-default" data-dismiss="modal">Zrušit</button>
                <button type="button" class="btn btn-success" id="mQualSave">Potvrdit schválení</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mDuplicateWarning" tabindex="-1" role="dialog" style="z-index: 1060;">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content modal-modern">
            <div class="modal-header modal-header-modern header-warning">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-warning-sign"></i> Pozor: Přesná shoda!</h4>
            </div>
            <div class="modal-body modal-body-modern">
                <p id="dupTextBody">Přesně tento požadavek již v systému běží <strong>(Požadavek #<span id="dupExistId"></span>)</strong>.</p>
                <p>Chcete k němu pouze přidat své zákazníky, nebo natvrdo založit další paralelní požadavek?</p>
            </div>
            <div class="modal-footer modal-footer-modern" style="text-align: center;">
                <button type="button" class="btn btn-default btn-block" id="btnDupGoTo">Zrušit a přejít na existující</button>
                <button type="button" class="btn btn-success btn-block" id="btnDupAppend" style="margin-top: 5px;">Jen připojit zákazníky</button>
                <button type="button" class="btn btn-warning btn-block" id="btnDupForce" style="margin-top: 5px;">Založit jako ÚPLNĚ NOVÝ</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mPingPurchasing" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content modal-modern">
            <div class="modal-header modal-header-modern header-info">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-bell"></i> Vyžádat další nabídku</h4>
            </div>
            <div class="modal-body modal-body-modern">
                <p>Chcete požádat Nákup o dohledání další alternativy pro surovinu <strong id="pingSurName"></strong>?</p>
                <p class="text-muted small">Nákupu přijde upozornění a požadavek se jim na nástěnce zvýrazní.</p>
                <input type="hidden" id="pingReqId">
                <input type="hidden" id="pingSurRaw">
            </div>
            <div class="modal-footer modal-footer-modern">
                <button type="button" class="btn btn-default" data-dismiss="modal">Zrušit</button>
                <button type="button" class="btn btn-info" id="btnConfirmPing">Ano, odeslat žádost</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mUrgeTaskModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content modal-modern">
            <div class="modal-header modal-header-modern header-warning">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-flash"></i> Urgovat řešení</h4>
            </div>
            <div class="modal-body modal-body-modern">
                <p>Chcete urgovat řešení tohoto požadavku?</p>
                <p class="text-muted small">Systém automaticky zjistí, u koho to momentálně stojí, a pošle příslušnému oddělení upozornění na Telegram.</p>
                <input type="hidden" id="mUrgeReqId">
                <input type="hidden" id="mUrgeSurRaw">
            </div>
            <div class="modal-footer modal-footer-modern">
                <button type="button" class="btn btn-default" data-dismiss="modal">Zrušit</button>
                <button type="button" class="btn btn-warning" id="btnConfirmUrge">Ano, urgovat</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mDeleteHistoryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content modal-modern shadow-danger">
            <div class="modal-header modal-header-modern header-danger">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-trash"></i> Smazat záznam</h4>
            </div>
            <div class="modal-body modal-body-modern">
                <p>Opravdu chcete smazat tuto poznámku?</p>
                <p class="text-muted small">Tuto akci nelze vzít zpět.</p>
                <input type="hidden" id="mDeleteHistoryId">
            </div>
            <div class="modal-footer modal-footer-modern">
                <button type="button" class="btn btn-default" data-dismiss="modal">Zrušit</button>
                <button type="button" class="btn btn-danger" id="mDeleteHistorySave">Ano, smazat</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mEditHistoryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content modal-modern">
            <div class="modal-header modal-header-modern header-primary">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-pencil"></i> Upravit poznámku</h4>
            </div>
            <div class="modal-body modal-body-modern">
                <input type="hidden" id="mEditHistoryId">
                <div class="form-group">
                    <label>Text poznámky:</label>
                    <textarea id="mEditHistoryText" class="form-control textarea-modern" rows="4"></textarea>
                </div>
            </div>
            <div class="modal-footer modal-footer-modern">
                <button type="button" class="btn btn-default" data-dismiss="modal">Zrušit</button>
                <button type="button" class="btn btn-primary" id="mEditHistorySave">Uložit změny</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mHistorieSuroviny" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content modal-modern">
            <div class="modal-header modal-header-modern header-info">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-history"></i> Historie pokusů: <span id="modalSurName"></span></h4>
            </div>
            <div class="modal-body modal-body-modern" id="modalSurBody">
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mSystemAlert" tabindex="-1" role="dialog" style="z-index: 100000;">
    <div class="modal-dialog modal-sm margin-top-15vh" role="document">
        <div class="modal-content modal-modern shadow-heavy">
            <div class="modal-header modal-header-modern header-primary" id="mSystemAlertHeader">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="mSystemAlertTitle"><i class="fa fa-info-circle"></i> Upozornění</h4>
            </div>
            <div class="modal-body modal-body-modern text-center" id="mSystemAlertBody" style="font-size: 15px; color: #444;">
            </div>
            <div class="modal-footer modal-footer-modern text-center" id="mSystemAlertFooter">
            </div>
        </div>
    </div>
</div>

<div class="feedback-btn-wrapper">
    <button class="btn btn-primary btn-feedback" id="btnOpenFeedback">
        <i class="glyphicon glyphicon-comment"></i> Nápady a úpravy
        <span class="badge-pulse" style="display: none;">!</span>
    </button>
</div>

<div class="modal fade" id="mFeedback" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content modal-modern feedback-modal-content">
            <div class="modal-header modal-header-modern header-primary">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-comment"></i> Nápady a úpravy nástěnky</h4>
            </div>
            <div class="modal-body modal-body-modern">
                <div class="feedback-form-box box-modern box-light">
                    <textarea id="feedbackText" class="form-control textarea-modern" rows="3" placeholder="Napadlo vás vylepšení? Narazili jste na chybu? Napište to sem..."></textarea>
                    <div style="text-align: right; margin-top: 10px;">
                        <button class="btn btn-success btn-modern btn-feedback-submit" id="btnSaveFeedback">Odeslat nápad</button>
                    </div>
                </div>
                <div class="feedback-list-title label-uppercase text-muted">Historie úprav a nápadů:</div>
                <div id="feedbackList" class="feedback-list-container">
                    <div class="feedback-msg"><i class="glyphicon glyphicon-refresh spinning"></i> Načítám...</div>
                </div>
            </div>
        </div>
    </div>
</div>