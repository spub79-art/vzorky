<?php
if (empty($_SESSION['username'])) exit;
?>

<div id="portfolio-dashboard" class="pf-dashboard">
    <div class="pf-toolbar">
        <div class="pf-toolbar-left">
            <span class="pf-toolbar-hint text-muted">
                <i class="glyphicon glyphicon-info-sign"></i>
                Klik = zvýraznění · filtr v každém sloupci · Esc zruší výběr
            </span>
            <span id="pfFilterChips" class="pf-filter-chips"></span>
            <button type="button" class="btn btn-sm btn-default" id="pfBtnSirotcinec" title="Požadavky bez produktu">
                <i class="glyphicon glyphicon-inbox"></i> Sirotčinec <span class="badge" id="pfSirotBadge">0</span>
            </button>
        </div>
    </div>

    <div id="pfMigrationWarn" class="alert alert-warning pf-migration-warn" style="display:none;">
        <i class="glyphicon glyphicon-warning-sign"></i>
        Chybí migrace databáze — spusťte <code>migrate_portfolio_produkty.sql</code>.
    </div>

    <div id="pfProductBar" class="pf-product-bar" style="display:none;"></div>

    <div id="pfSirotcinecPanel" class="pf-sirotcinec-panel" style="display:none;">
        <div class="pf-sirotcinec-head">
            <strong><i class="glyphicon glyphicon-inbox"></i> Sirotčinec</strong>
            <span class="text-muted"> — otevřené požadavky bez aktivního produktu (fáze zachována)</span>
            <div class="pf-sirotcinec-head-right">
                <input type="text" id="pfFilterSirot" class="pf-col-filter-input" placeholder="Filtrovat suroviny…" style="width:200px;display:inline-block;">
                <button type="button" class="btn btn-xs btn-default" id="pfBtnSirotClose">Zpět na portfolio</button>
            </div>
        </div>
        <div class="pf-sirotcinec-body" id="pfListSirotcinec"></div>
    </div>

    <div class="pf-columns" id="pfColumns">
        <div class="pf-col pf-col-zak" data-pf-col="zakaznici">
            <div class="pf-col-head">
                <span class="pf-col-title"><i class="glyphicon glyphicon-user"></i> Zákazníci</span>
                <div class="pf-col-head-actions">
                    <span class="pf-col-count" id="pfCountZak">0</span>
                    <button type="button" class="btn btn-xs btn-success pf-col-add" id="pfBtnNewZak" title="Nový zákazník">+</button>
                </div>
            </div>
            <div class="pf-col-filter">
                <input type="text" id="pfFilterZak" class="pf-col-filter-input" placeholder="Filtrovat…" autocomplete="off">
            </div>
            <div class="pf-col-body" id="pfListZakaznici"></div>
        </div>

        <div class="pf-col pf-col-prod" data-pf-col="produkty">
            <div class="pf-col-head">
                <span class="pf-col-title"><i class="glyphicon glyphicon-th-list"></i> Produkty</span>
                <div class="pf-col-head-actions">
                    <span class="pf-col-count" id="pfCountProd">0</span>
                    <button type="button" class="btn btn-xs btn-primary pf-col-add" id="pfBtnNewProd" title="Nový produkt">+</button>
                </div>
            </div>
            <div class="pf-col-filter pf-col-filter-row">
                <input type="text" id="pfFilterProd" class="pf-col-filter-input" placeholder="Filtrovat…" autocomplete="off">
                <button type="button" class="btn btn-xs btn-default pf-col-filter-btn" id="pfFilterUrgent" title="Jen urgentní">
                    <i class="glyphicon glyphicon-flash text-danger"></i>
                </button>
                <button type="button" class="btn btn-xs btn-default pf-col-filter-btn" id="pfFilterEnded" title="Zobrazit ukončené · 2× klik ↺ = obnovit">
                    <i class="glyphicon glyphicon-eye-open"></i>
                </button>
            </div>
            <div class="pf-col-body" id="pfListProdukty"></div>
        </div>

        <div class="pf-col pf-col-sur" data-pf-col="sur">
            <div class="pf-col-head">
                <span class="pf-col-title"><i class="glyphicon glyphicon-ok-circle"></i> Suroviny</span>
                <span class="pf-col-count" id="pfCountAssigned">0</span>
            </div>
            <div class="pf-col-filter">
                <input type="text" id="pfFilterSurAssigned" class="pf-col-filter-input" placeholder="Filtrovat…" autocomplete="off" disabled>
            </div>
            <div class="pf-col-body" id="pfListAssigned">
                <div class="pf-empty-hint">Vyberte produkt</div>
            </div>
        </div>

        <div class="pf-col pf-col-detail" data-pf-col="detail">
            <div class="pf-col-head">
                <span class="pf-col-title"><i class="glyphicon glyphicon-comment"></i> Detail &amp; diskuze</span>
            </div>
            <div class="pf-col-body pf-col-body-detail" id="pfColDetailWrap">
                <div id="pfDetailPlaceholder" class="pf-empty-hint">Vyberte produkt — zde bude poznámka a diskuze</div>
                <div id="pfProductNotes" class="pf-detail-block" style="display:none;">
                    <div class="pf-stack-label">Poznámka k produktu</div>
                    <textarea id="pfPoznamkaText" class="form-control pf-poznamka-input" rows="4" placeholder="Interní poznámka…"></textarea>
                    <button type="button" class="btn btn-xs btn-primary" id="pfPoznamkaSave" style="margin-top:6px;">Uložit poznámku</button>
                </div>
                <div id="pfProductChat" class="pf-detail-block pf-detail-chat" style="display:none;">
                    <div class="pf-stack-label">Diskuze</div>
                    <div id="pfChatList" class="pf-chat-list"></div>
                    <div class="input-group input-group-sm pf-chat-input-row">
                        <input type="text" id="pfChatInput" class="form-control" placeholder="Napsat zprávu…">
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-primary" id="pfChatSend">Odeslat</button>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="pf-col pf-col-catalog" data-pf-col="catalog">
            <div class="pf-col-head">
                <span class="pf-col-title"><i class="glyphicon glyphicon-plus-sign"></i> Přidat surovinu</span>
                <span class="pf-col-count" id="pfCountCatalog">0</span>
            </div>
            <div class="pf-col-filter">
                <input type="text" id="pfFilterSurCatalog" class="pf-col-filter-input" placeholder="Filtrovat katalog…" autocomplete="off" disabled>
            </div>
            <div class="pf-col-body" id="pfListCatalog">
                <div class="pf-empty-hint">Vyberte produkt vlevo</div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: zákazník -->
<div class="modal fade" id="pfModalZak" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header" style="background:#337ab7;color:#fff;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;">&times;</button>
                <h4 class="modal-title" id="pfModalZakTitle">Nový zákazník</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="pfZakId" value="0">
                <div class="form-group">
                    <label>Název zákazníka</label>
                    <input type="text" id="pfZakNazev" class="form-control" placeholder="např. Boon Bar">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Zrušit</button>
                <button type="button" class="btn btn-primary" id="pfZakSave">Uložit</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: produkt -->
<div class="modal fade" id="pfModalProd" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:#5bc0de;color:#fff;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;">&times;</button>
                <h4 class="modal-title" id="pfModalProdTitle">Nový produkt</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="pfProdId" value="0">
                <div class="form-group">
                    <label>Zákazník</label>
                    <select id="pfProdZakaznik" class="form-control"></select>
                </div>
                <div class="form-group">
                    <label>Název produktu</label>
                    <input type="text" id="pfProdNazev" class="form-control" placeholder="např. Proteinová tyčinka DM">
                </div>
                <div class="form-group">
                    <label>Priorita</label>
                    <select id="pfProdPriorita" class="form-control">
                        <option value="0">Normální</option>
                        <option value="1">Urgentní</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Zrušit</button>
                <button type="button" class="btn btn-primary" id="pfProdSave">Uložit</button>
            </div>
        </div>
    </div>
</div>
