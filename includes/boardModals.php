<div class="modal fade" id="mWF" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
            <div class="modal-header" style="color:#fff; background:#337ab7;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Správa položky</h4>
            </div>
            <div class="modal-body">
                <div id="mWFSarzeBox" style="display:none; background: #fff4e5; padding: 12px; border: 1px solid #f0ad4e; border-radius: 6px; margin-bottom: 15px;">
                    <label class="text-danger small">Šarže dodavatele (Povinné k analýze):</label>
                    <input type="text" id="mWFSarze" class="form-control input-sm">
                </div>

                <div id="mWFUploadBox" class="panel-group" style="margin-bottom: 15px;">
                    <div class="panel panel-default">
                        <div class="panel-heading" style="display:flex; justify-content:space-between; align-items:center; padding: 5px 10px;">
                            <h5 style="margin:0; font-size:13px;"><i class="glyphicon glyphicon-list-alt text-primary"></i> TDS (Specifikace)</h5>
                            <button class="btn btn-xs btn-success btn-toggle-upload" data-target="TDS"><i class="glyphicon glyphicon-plus"></i></button>
                        </div>
                        <div class="panel-body" style="padding:10px;"><div id="listTDS"></div><div id="uploadTDS" style="display:none; margin-top:10px;"><input type="file" id="mWFFileSpec" multiple class="form-control input-sm"></div></div>
                    </div>
                    <div class="panel panel-default">
                        <div class="panel-heading" style="display:flex; justify-content:space-between; align-items:center; padding: 5px 10px;">
                            <h5 style="margin:0; font-size:13px;"><i class="glyphicon glyphicon-tint text-danger"></i> COA (Analýza / Lab)</h5>
                            <button class="btn btn-xs btn-success btn-toggle-upload" data-target="COA"><i class="glyphicon glyphicon-plus"></i></button>
                        </div>
                        <div class="panel-body" style="padding:10px;"><div id="listCOA"></div><div id="uploadCOA" style="display:none; margin-top:10px;"><input type="file" id="mWFFileLab" multiple class="form-control input-sm"></div></div>
                    </div>
                    <div class="panel panel-default">
                        <div class="panel-heading" style="display:flex; justify-content:space-between; align-items:center; padding: 5px 10px;">
                            <h5 style="margin:0; font-size:13px;"><i class="glyphicon glyphicon-file text-muted"></i> Ostatní přílohy</h5>
                            <button class="btn btn-xs btn-success btn-toggle-upload" data-target="Other"><i class="glyphicon glyphicon-plus"></i></button>
                        </div>
                        <div class="panel-body" style="padding:10px;"><div id="listOther"></div><div id="uploadOther" style="display:none; margin-top:10px;"><input type="file" id="mWFFileOther" multiple class="form-control input-sm"></div></div>
                    </div>
                </div>

                <div id="mWFQtyBox" style="display:none;" class="form-group"><label class="small">Množství k objednání:</label><input type="text" id="mWFQty" class="form-control input-sm"></div>
                <div id="mWFItemCodeBox" style="display:none; border: 1px solid #28a745; padding: 10px; border-radius: 5px; background: #f8fff8; margin-bottom: 10px;"><div class="row"><div class="col-xs-6"><input type="text" id="mWFSkupZbo" class="form-control input-sm" placeholder="skupzbo"></div><div class="col-xs-6"><input type="text" id="mWFRegCis" class="form-control input-sm" placeholder="regcis"></div></div></div>

                <div id="mWFFileStatus"></div>
                <button type="button" class="btn btn-success btn-block" id="btnDoUpload" style="display:none;"><i class="glyphicon glyphicon-upload"></i> Nahrát vybrané soubory</button>
                <div class="form-group" style="margin-top:15px;"><label class="small">Poznámka:</label><textarea id="mWFNote" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="mWFSave">Potvrdit a Zavřít</button>
            </div>
        </div></div></div>

<div class="modal fade" id="mReason" tabindex="-1" style="z-index: 9999;"><div class="modal-dialog modal-sm"><div class="modal-content">
            <div class="modal-header" style="background:#d9534f; color:#fff;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Zadejte důvod</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="mReasonId">
                <input type="hidden" id="mReasonStatus">
                <div class="form-group">
                    <label class="small">Vysvětlení pro kolegy:</label>
                    <textarea id="mReasonText" class="form-control" rows="3" placeholder="Proč je to KO nebo co chybí?"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary btn-block" id="mReasonSave">Potvrdit akci</button>
            </div>
        </div></div></div>

<div class="modal fade" id="mNN" tabindex="-1"><div class="modal-dialog modal-sm"><div class="modal-content">
            <div class="modal-header" style="background:#337ab7; color:#fff;"><button type="button" class="close" data-dismiss="modal">&times;</button><h4>Nová nabídka</h4></div>
            <div class="modal-body">
                <input type="hidden" id="mNNId"><div class="form-group"><label>Dodavatel:</label>
                    <select id="mNNDod" class="form-control select2-dod" style="width:100%;"><option value="">-- Vyberte --</option><?php $d_res = mysqli_query($conn, "SELECT nazev FROM dodavatele ORDER BY nazev ASC"); while($d = mysqli_fetch_assoc($d_res)) echo "<option value='".htmlspecialchars($d['nazev'])."'>".htmlspecialchars($d['nazev'])."</option>"; ?></select></div>
                <div class="form-group"><label>Cena (CZK):</label><input type="number" id="mNNCena" class="form-control" step="0.01"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-primary btn-block" id="mNNSave">Uložit nabídku</button></div>
        </div></div></div>
<div class="modal fade" id="mQtyNote" tabindex="-1" style="z-index: 9999;">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header" style="background:#28a745; color:#fff;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Schválit cenu a objednat vzorek</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="mQNId">
                <input type="hidden" id="mQNStatus">

                <div class="form-group">
                    <label class="small">Požadované množství (povinné): ⚖️</label>
                    <input type="text" id="mQNQty" class="form-control" placeholder="např. 1 kg nebo 500 ml">
                </div>

                <div class="form-group">
                    <label class="small">Poznámka pro nákup: ✉️</label>
                    <textarea id="mQNNote" class="form-control" rows="3" placeholder="Instrukce k objednávce..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success btn-block" id="mQNSave">Potvrdit schválení</button>
            </div>
        </div>
    </div>
</div>