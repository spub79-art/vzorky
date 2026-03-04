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
                            <button class="btn btn-xs btn-success btn-toggle-upload" data-target="TDS" style="border-radius: 4px;"><i class="glyphicon glyphicon-plus"></i></button>
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
                            <button class="btn btn-xs btn-success btn-toggle-upload" data-target="COA" style="border-radius: 4px;"><i class="glyphicon glyphicon-plus"></i></button>
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
                            <button class="btn btn-xs btn-success btn-toggle-upload" data-target="Other" style="border-radius: 4px;"><i class="glyphicon glyphicon-plus"></i></button>
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
    <div class="modal-dialog" style="width: 400px;">
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
                    <label class="small text-muted" style="text-transform: uppercase;">Cena za MJ (CZK bez DPH)</label>
                    <input type="number" id="mNNCena" class="form-control input-lg" step="0.01" placeholder="Zadejte cenu..." style="border-radius: 8px;">
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

                <div class="form-group" style="margin-top: 20px;">
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

<script>
    // JS Logika pro zrušení požadavku
    $(document).ready(function() {
        // Kliknutí na "ZRUŠIT" uvnitř editace
        $('#btnOpenCancelReq').click(function() {
            var reqId = $('#mEditReqId').val();
            $('#mCancelReqId').val(reqId);
            $('#mCancelReqReason').val(''); // Vyčistíme text

            // Skryjeme editační okno a ukážeme to pro zrušení
            $('#mEditReq').modal('hide');
            setTimeout(function() {
                $('#mCancelReqModal').modal('show');
            }, 400); // Počkáme na dokončení animace zavírání
        });

        // Potvrzení zrušení v malém modálu
        $('#mCancelReqSave').click(function() {
            var id = $('#mCancelReqId').val();
            var reason = $('#mCancelReqReason').val().trim();

            if(!reason) {
                alert("Vyplňte prosím důvod zrušení.");
                return;
            }

            // Odeslání do nového skriptu
            $.post('includes/ajax_cancel_request.php', { id: id, poznamka: reason }, function(r) {
                if(r.trim() === "OK") {
                    $('#mCancelReqModal').modal('hide');
                    if(typeof safeReload === "function") safeReload(); else window.location.reload();
                } else {
                    alert(r);
                }
            });
        });
    });
</script>