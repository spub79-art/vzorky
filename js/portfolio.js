(function($) {
    'use strict';

    var state = {
        data: null,
        selZak: null,
        selProd: null,
        filterZak: '',
        filterProd: '',
        filterSurAssigned: '',
        filterSurCatalog: '',
        filterSirot: '',
        filterUrgent: false,
        filterEnded: false,
        viewSirotcinec: false,
        armedUnlinkId: null,
        armedEndProdId: null,
        armedRestoreProdId: null,
        productExtras: null
    };

    function esc(s) {
        return $('<div>').text(s || '').html();
    }

    function getProduct(id) {
        if (!state.data || id == null || id === '') return null;
        var nid = parseInt(id, 10);
        return state.data.produkty.find(function(x) { return parseInt(x.id, 10) === nid; }) || null;
    }

    function zakLabel(id) {
        if (id === 0) return '— Nepřiřazené —';
        if (!state.data) return '?';
        var z = state.data.zakaznici.find(function(x) { return x.id === id; });
        return z ? z.nazev : '?';
    }

    function matchText(text, filter) {
        if (!filter) return true;
        return (text || '').toLowerCase().indexOf(filter) !== -1;
    }

    function loadTree(cb) {
        $.getJSON('includes/ajax_portfolio.php', { action: 'tree' })
            .done(function(d) {
                if (d.error) { alert(d.error); return; }
                state.data = d;
                $('#pfMigrationWarn').toggle(!d.migration_ok);
                $('#pfSirotBadge').text(d.sirotcinec_count || 0);
                if (state.viewSirotcinec) loadSirotcinec();
                renderAll();
                if (cb) cb();
            })
            .fail(function() { alert('Nepodařilo se načíst data portfolia.'); });
    }

    function allZakaznici() {
        if (!state.data) return [];
        var list = state.data.zakaznici.slice();
        if (state.data.neprirazeno.pocet_produktu > 0 || state.filterEnded) {
            list.push({
                id: 0,
                nazev: '— Nepřiřazené —',
                pocet_produktu: state.data.neprirazeno.pocet_produktu,
                pocet_urgent: state.data.neprirazeno.pocet_urgent
            });
        }
        return list;
    }

    function zakDimmed(z) {
        if (state.selZak !== null && z.id !== state.selZak) return true;
        if (state.selProd) {
            var p = getProduct(state.selProd);
            if (p && z.id !== p.id_zakaznik) return true;
        }
        return false;
    }

    function prodPassesFilters(p) {
        if (!state.filterEnded && p.ukonceny) return false;
        if (state.filterUrgent && !p.priorita) return false;
        if (state.filterProd) {
            if (matchText(p.nazev, state.filterProd)) return true;
            if (matchText(zakLabel(p.id_zakaznik), state.filterProd)) return true;
            var surs = (state.data.suroviny[p.id] || []);
            for (var i = 0; i < surs.length; i++) {
                if (matchText(surs[i].nazev, state.filterProd) || matchText(surs[i].nazev_en, state.filterProd)) return true;
            }
            return false;
        }
        return true;
    }

    function prodDimmed(p) {
        if (state.selZak !== null && p.id_zakaznik !== state.selZak) return true;
        if (state.selProd !== null && p.id !== state.selProd) return true;
        return false;
    }

    function linkedSurIds(prodId) {
        var map = {};
        (state.data.suroviny[prodId] || []).forEach(function(s) { map[s.id_surovina] = true; });
        return map;
    }

    function linkedReqIds(prodId) {
        var list = (state.data && state.data.linked_requests_by_prod)
            ? (state.data.linked_requests_by_prod[prodId] || []) : [];
        var map = {};
        list.forEach(function(id) { map[id] = true; });
        return map;
    }

    function catalogCandidates(sid, prodId) {
        var open = (state.data && state.data.open_requests_by_surovina)
            ? (state.data.open_requests_by_surovina[sid] || []) : [];
        var linked = linkedReqIds(prodId);
        return open.filter(function(r) { return !linked[r.req_id]; });
    }

    function renderCatalogReqRow(req, sid, clickable) {
        var cls = clickable ? 'pf-catalog-req pf-catalog-req-click' : 'pf-catalog-req';
        var phase = req.phase ? ' <span class="pf-catalog-phase">fáze ' + req.phase + '</span>' : '';
        var rd = {
            req_id: req.req_id,
            priorita: req.priorita,
            bio: req.bio,
            vegan: req.vegan,
            bezlepek: req.bezlepek,
            kosher: req.kosher,
            halal: req.halal
        };
        return '<div class="' + cls + '" data-req="' + req.req_id + '" data-sid="' + sid + '" title="' + esc(req.label || '') + '">' +
            '<span class="pf-sur-req-num">#' + req.req_id + '</span>' +
            reqCertBadges(rd) +
            phase +
            ' <span class="pf-catalog-req-label">' + esc(req.label || '') + '</span>' +
            (clickable ? ' <span class="pf-catalog-req-add"><i class="glyphicon glyphicon-plus"></i></span>' : '') +
            '</div>';
    }

    function doLinkPozadavek(reqId, $busy, onDone) {
        if (!state.selProd) {
            alert('Nejdříve vyberte produkt.');
            return;
        }
        if ($busy) $busy.addClass('pf-card-adding');
        $.post('includes/ajax_portfolio.php', {
            action: 'link_pozadavek',
            id_pozadavek: reqId,
            id_produkt: state.selProd
        }, function(r) {
            if (typeof r === 'string') r = JSON.parse(r);
            if (r.error) {
                alert(r.error);
                if ($busy) $busy.removeClass('pf-card-adding');
                return;
            }
            reloadKeepingSelection(onDone);
        }, 'json');
    }

    function renderFilterChips() {
        var $c = $('#pfFilterChips').empty();
        if (state.selZak !== null) {
            $c.append('<span class="pf-chip">Zákazník: ' + esc(zakLabel(state.selZak)) +
                ' <a href="#" class="pf-chip-clear" data-clear="zak">×</a></span>');
        }
        if (state.selProd) {
            var p = getProduct(state.selProd);
            if (p) {
                $c.append('<span class="pf-chip">Produkt: ' + esc(p.nazev) +
                    ' <a href="#" class="pf-chip-clear" data-clear="prod">×</a></span>');
            }
        }
    }

    function readinessSummaryLine(p) {
        var rs = p.readiness_summary;
        if (!rs || !rs.total) return '';
        var parts = [rs.total + ' surovin'];
        if (rs.ok > 0) parts.push(rs.ok + ' OK');
        if (rs.blocking > 0) parts.push('<span class="pf-rs-block">' + rs.blocking + ' brzdí</span>');
        return parts.join(' · ');
    }

    function surReadinessClasses(s, produkt) {
        var rd = s.readiness || {};
        var cls = ' pf-sur-st-' + (rd.state || 'missing');
        if (rd.state === 'ok') cls += ' pf-sur-is-ok';
        if (rd.blocking && produkt && produkt.priorita && !produkt.ukonceny) {
            cls += ' pf-sur-blocks';
        }
        return cls;
    }

    function reqCertBadges(rd) {
        if (!rd) return '';
        var parts = [];
        if (rd.priorita) parts.push('<span class="label label-danger pf-cert-badge">URG</span>');
        if (rd.bio) parts.push('<span class="label label-success pf-cert-badge">BIO</span>');
        if (rd.vegan) parts.push('<span class="label label-success pf-cert-badge">VEG</span>');
        if (rd.bezlepek) parts.push('<span class="label label-warning pf-cert-badge">BL</span>');
        if (rd.kosher) parts.push('<span class="label pf-cert-badge pf-cert-kosher">K</span>');
        if (rd.halal) parts.push('<span class="label pf-cert-badge pf-cert-halal">H</span>');
        return parts.length ? '<span class="pf-sur-certs">' + parts.join('') + '</span>' : '';
    }

    function renderSurReqRow(rd, candidates) {
        if (rd && rd.req_id) {
            return '<div class="pf-sur-req-row">' +
                '<a href="index.php?Pozadavek=1&amp;req_id=' + rd.req_id + '" class="pf-sur-req-num" target="_blank" title="Otevřít požadavek">#' + rd.req_id + '</a>' +
                reqCertBadges(rd) +
                '</div>';
        }
        var html = '<div class="pf-sur-req-row pf-sur-req-row-missing"><span class="pf-sur-req-none">nepřipojený požadavek</span></div>';
        if (candidates && candidates.length) {
            html += '<div class="pf-sur-candidates">';
            candidates.forEach(function(c) {
                html += '<button type="button" class="btn btn-xs btn-success pf-link-candidate" data-req="' + c.req_id + '">' +
                    'Připojit #' + c.req_id + '</button> ';
            });
            html += '</div>';
        }
        return html;
    }

    function renderProductBar() {
        var $bar = $('#pfProductBar');
        if (!state.selProd || !state.data || state.viewSirotcinec) {
            $bar.hide().empty();
            return;
        }
        var p = getProduct(state.selProd);
        if (!p) {
            $bar.hide();
            return;
        }

        var urgent = p.priorita ? ' <span class="label label-danger">URGENT</span>' : '';
        var ended = p.ukonceny ? ' <span class="label label-default">Ukončený</span>' : '';
        var rs = p.readiness_summary;
        var blockHint = (rs && rs.blocks_product)
            ? ' <span class="label label-warning pf-label-blocks"><i class="glyphicon glyphicon-warning-sign"></i> brzdí vývoj</span>'
            : '';
        var html = '<div class="pf-product-bar-inner">' +
            '<strong>' + esc(p.nazev) + '</strong>' + urgent + ended + blockHint +
            ' <span class="pf-product-bar-zak"><i class="glyphicon glyphicon-user"></i> ' + esc(zakLabel(p.id_zakaznik)) + '</span>' +
            '<span class="pf-product-bar-actions">' +
            '<button type="button" class="btn btn-xs btn-default pf-btn-edit-prod" title="Upravit produkt"><i class="glyphicon glyphicon-pencil"></i></button>' +
            '</span></div>';
        if (rs && rs.total > 0) {
            html += '<div class="pf-product-bar-readiness">' + readinessSummaryLine(p) + '</div>';
        }
        $bar.html(html).show();
    }

    function doEndProduct(prodId) {
        prodId = parseInt(prodId, 10);
        if (!prodId || !state.data) return;
        if (!state.data.migration_ok) {
            alert('Spusťte migraci migrate_portfolio_produkty.sql');
            return;
        }
        var p = getProduct(prodId);
        if (!p || p.ukonceny) return;

        $.ajax({
            url: 'includes/ajax_portfolio.php',
            type: 'POST',
            data: { action: 'end_produkt', id: prodId },
            dataType: 'json'
        }).done(function(r) {
            if (!r || r.error) {
                alert((r && r.error) ? r.error : 'Nepodařilo se ukončit produkt.');
                return;
            }
            state.armedEndProdId = null;
            if (parseInt(state.selProd, 10) === prodId) state.selProd = null;
            loadTree();
        }).fail(function(xhr) {
            var msg = 'Chyba při ukončování produktu.';
            if (xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
            else if (xhr.responseText) msg += ' ' + xhr.responseText.substring(0, 120);
            alert(msg);
        });
    }

    function doRestoreProduct(prodId) {
        prodId = parseInt(prodId, 10);
        if (!prodId || !state.data) return;
        if (!state.data.migration_ok) {
            alert('Spusťte migraci migrate_portfolio_produkty.sql');
            return;
        }
        var p = getProduct(prodId);
        if (!p || !p.ukonceny) return;

        $.ajax({
            url: 'includes/ajax_portfolio.php',
            type: 'POST',
            data: { action: 'restore_produkt', id: prodId },
            dataType: 'json'
        }).done(function(r) {
            if (!r || r.error) {
                alert((r && r.error) ? r.error : 'Nepodařilo se obnovit produkt.');
                return;
            }
            state.armedRestoreProdId = null;
            loadTree(function() {
                state.selProd = prodId;
                renderAll();
            });
        }).fail(function(xhr) {
            var msg = 'Chyba při obnově produktu.';
            if (xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
            else if (xhr.responseText) msg += ' ' + xhr.responseText.substring(0, 120);
            alert(msg);
        });
    }

    function renderZakaznici() {
        var $el = $('#pfListZakaznici').empty();
        var list = allZakaznici();
        var visible = 0;

        if (!list.length) {
            $el.html('<div class="pf-empty-hint">Žádní zákazníci — tlačítko <strong>+</strong> v záhlaví</div>');
            $('#pfCountZak').text('0');
            return;
        }

        list.forEach(function(z) {
            if (!matchText(z.nazev, state.filterZak)) return;
            visible++;
            var urgent = z.pocet_urgent > 0 ? '<span class="pf-badge-urgent"><i class="glyphicon glyphicon-flash"></i> ' + z.pocet_urgent + '</span>' : '';
            var cls = 'pf-card pf-card-zak';
            if (state.selZak === z.id) cls += ' is-selected';
            if (zakDimmed(z)) cls += ' pf-card-dimmed';
            if (z.id === 0) cls += ' pf-card-muted';
            var $card = $('<div class="' + cls + '" data-id="' + z.id + '">' +
                '<div class="pf-card-title">' + esc(z.nazev) + '</div>' +
                '<div class="pf-card-meta">' + z.pocet_produktu + ' aktivních ' + urgent + '</div></div>');
            if (z.id > 0) {
                $card.append('<button type="button" class="pf-card-edit btn btn-xs btn-link" title="Upravit"><i class="glyphicon glyphicon-pencil"></i></button>');
            }
            $el.append($card);
        });

        $('#pfCountZak').text(visible);
        if (!visible) $el.html('<div class="pf-empty-hint">Nic nevyhovuje filtru</div>');
    }

    function renderProdukty() {
        var $el = $('#pfListProdukty').empty();
        if (!state.data) return;

        var visible = 0;
        state.data.produkty.forEach(function(p) {
            if (!prodPassesFilters(p)) return;
            visible++;
            var urgentCls = p.priorita ? ' pf-card-urgent' : '';
            var endedCls = p.ukonceny ? ' pf-card-ended' : '';
            var blocksCls = (p.readiness_summary && p.readiness_summary.blocks_product) ? ' pf-card-blocks' : '';
            var selCls = state.selProd === p.id ? ' is-selected' : '';
            var dimCls = prodDimmed(p) ? ' pf-card-dimmed' : '';
            var zakLine = (state.selZak === null) ? '<div class="pf-card-zak-line">' + esc(zakLabel(p.id_zakaznik)) + '</div>' : '';
            var rsLine = readinessSummaryLine(p);
            var metaLine = rsLine
                ? '<div class="pf-card-meta pf-card-meta-readiness">' + rsLine + (p.ukonceny ? ' · ukončeno' : '') + '</div>'
                : '<div class="pf-card-meta">' + p.pocet_surovin + ' surovin' + (p.ukonceny ? ' · ukončeno' : '') + '</div>';
            var actionBtns = '<button type="button" class="pf-prio-toggle btn btn-xs" title="Přepnout urgentní"><i class="glyphicon glyphicon-flash"></i></button>';
            if (state.data.migration_ok) {
                if (!p.ukonceny) {
                    actionBtns += '<button type="button" class="pf-prod-end btn btn-xs btn-link" data-id="' + p.id + '" title="2× klik = ukončit produkt">×</button>';
                } else {
                    actionBtns += '<button type="button" class="pf-prod-restore btn btn-xs btn-link" data-id="' + p.id + '" title="2× klik = obnovit produkt"><i class="glyphicon glyphicon-repeat"></i></button>';
                }
            }
            var $card = $('<div class="pf-card pf-card-prod' + urgentCls + endedCls + blocksCls + selCls + dimCls + '" data-id="' + p.id + '">' +
                '<div class="pf-card-prod-main">' +
                '<div class="pf-card-title">' + esc(p.nazev) + '</div>' +
                zakLine +
                metaLine +
                '</div>' +
                '<div class="pf-card-prod-actions">' + actionBtns + '</div>' +
                '</div>');
            $el.append($card);
        });

        if (state.armedEndProdId) {
            $el.find('.pf-prod-end[data-id="' + state.armedEndProdId + '"]').addClass('pf-end-armed');
        }
        if (state.armedRestoreProdId) {
            $el.find('.pf-prod-restore[data-id="' + state.armedRestoreProdId + '"]').addClass('pf-restore-armed');
        }

        $('#pfCountProd').text(visible);
        if (!visible) {
            var hint = 'Žádný produkt' + (state.filterProd || state.filterUrgent ? ' pro filtr' : '');
            if (!state.filterEnded) {
                hint += ' — ukončené zobrazíte ikonou <i class="glyphicon glyphicon-eye-open"></i>';
            }
            $el.html('<div class="pf-empty-hint">' + hint + '</div>');
        }
    }

    function renderAssigned() {
        var $el = $('#pfListAssigned').empty();
        var hasProd = !!(state.selProd && state.data);
        $('#pfFilterSurAssigned').prop('disabled', !hasProd);

        if (!hasProd) {
            $('#pfCountAssigned').text('0');
            $('#pfProductNotes, #pfProductChat').hide();
            $('#pfDetailPlaceholder').show();
            $el.html('<div class="pf-empty-hint">Vyberte produkt</div>');
            return;
        }

        $('#pfDetailPlaceholder').hide();
        $('#pfProductNotes, #pfProductChat').toggle(!!(state.data && state.data.notes_ok));

        var p = getProduct(state.selProd);
        var surs = (state.data.suroviny[p.id] || []).filter(function(s) {
            return matchText(s.nazev, state.filterSurAssigned) || matchText(s.nazev_en, state.filterSurAssigned);
        });

        $('#pfCountAssigned').text(surs.length);

        if (!surs.length) {
            $el.html('<div class="pf-empty-hint">Zatím žádné suroviny — přidejte z katalogu vpravo →</div>');
            return;
        }

        surs.forEach(function(s) {
            var rd = s.readiness || {};
            var en = s.nazev_en ? '<div class="pf-card-meta">' + esc(s.nazev_en) + '</div>' : '';
            var rm = p.ukonceny ? '' : '<button type="button" class="pf-sur-remove btn btn-xs btn-link text-danger" data-link="' + s.link_id + '" title="Klikněte 2× pro odebrání">×</button>';
            var stCls = surReadinessClasses(s, p);
            var statusLine = '<div class="pf-sur-status" title="' + esc(rd.label || '') + '">' +
                '<span class="pf-sur-status-dot"></span> ' + esc(rd.label || '—') + '</div>';
            var $card = $('<div class="pf-card pf-card-sur pf-card-sur-assigned' + stCls + '" data-link="' + s.link_id + '">' +
                rm +
                '<div class="pf-card-title">' + esc(s.nazev) + '</div>' +
                en +
                renderSurReqRow(rd, s.candidate_requests) +
                statusLine +
                '</div>');
            $el.append($card);
        });
        if (state.armedUnlinkId) {
            $el.find('[data-link="' + state.armedUnlinkId + '"] .pf-sur-remove').addClass('pf-remove-armed');
        }
    }

    function renderChat() {
        var $list = $('#pfChatList').empty();
        var msgs = (state.productExtras && state.productExtras.komentare) ? state.productExtras.komentare : [];
        if (!msgs.length) {
            $list.html('<div class="pf-chat-empty text-muted">Zatím žádné zprávy</div>');
            return;
        }
        msgs.forEach(function(m) {
            var dt = m.vytvoreno ? m.vytvoreno.substring(0, 16) : '';
            $list.append('<div class="pf-chat-msg"><div class="pf-chat-meta"><strong>' + esc(m.jmeno) + '</strong> <span>' + esc(dt) + '</span></div><div class="pf-chat-text">' + esc(m.text) + '</div></div>');
        });
        $list.scrollTop($list[0].scrollHeight);
    }

    function loadProductExtras(prodId) {
        if (!prodId || !state.data || !state.data.notes_ok) return;
        var p = getProduct(prodId);
        if (p && p.poznamka !== undefined) $('#pfPoznamkaText').val(p.poznamka || '');
        $.getJSON('includes/ajax_portfolio.php', { action: 'product_extras', id: prodId })
            .done(function(d) {
                if (d.error) return;
                state.productExtras = d;
                $('#pfPoznamkaText').val(d.poznamka || '');
                renderChat();
            });
    }

    function loadSirotcinec() {
        $.getJSON('includes/ajax_portfolio.php', { action: 'orphans', q: state.filterSirot })
            .done(function(d) {
                if (d.error) { alert(d.error); return; }
                renderSirotcinecList(d.items || []);
            });
    }

    function renderSirotcinecList(items) {
        var $el = $('#pfListSirotcinec').empty();
        if (!items.length) {
            $el.html('<div class="pf-empty-hint">Žádné osiřelé požadavky</div>');
            return;
        }
        var prod = state.selProd ? getProduct(state.selProd) : null;
        items.forEach(function(it) {
            var rd = {
                req_id: it.id,
                priorita: it.priorita,
                bio: it.bio,
                vegan: it.vegan,
                bezlepek: it.bezlepek,
                kosher: it.kosher,
                halal: it.halal
            };
            var en = it.surovina_en ? '<div class="pf-card-meta">' + esc(it.surovina_en) + '</div>' : '';
            var linkBtn = '';
            if (prod) {
                linkBtn = '<button type="button" class="btn btn-xs btn-success pf-link-orphan" data-req="' + it.id + '">→ ' + esc(prod.nazev) + '</button>';
            }
            var $card = $('<div class="pf-card pf-card-sirot" style="border-left-color:' + esc(it.barva_hex) + '">' +
                '<div class="pf-card-row"><span class="pf-card-title">' + esc(it.surovina) + '</span>' +
                '<span class="label label-default">' + esc(it.status_nazev) + '</span></div>' +
                en +
                renderSurReqRow(rd) +
                '<div class="pf-card-meta">Fáze zachována · bez aktivního produktu</div>' +
                '<div class="pf-sirot-actions">' + linkBtn +
                ' <a href="index.php?Pozadavek=1&amp;req_id=' + it.id + '" class="btn btn-xs btn-default" target="_blank">Detail</a></div></div>');
            $el.append($card);
        });
    }

    function setSirotcinecView(on) {
        state.viewSirotcinec = !!on;
        $('#pfSirotcinecPanel').toggle(on);
        $('#pfColumns').toggle(!on);
        $('#pfBtnSirotcinec').toggleClass('active', on);
        renderProductBar();
        if (on) loadSirotcinec();
    }

    function doUnlink(linkId) {
        state.armedUnlinkId = null;
        $('.pf-sur-remove').removeClass('pf-remove-armed');
        $.post('includes/ajax_portfolio.php', { action: 'unlink_surovina', link_id: linkId }, function(r) {
            if (typeof r === 'string') r = JSON.parse(r);
            if (r.error) { alert(r.error); reloadKeepingSelection(); return; }
            reloadKeepingSelection();
        }, 'json');
    }

    function renderCatalog() {
        var $el = $('#pfListCatalog').empty();
        var p = state.selProd ? getProduct(state.selProd) : null;
        var canAdd = !!(p && !p.ukonceny);
        $('#pfFilterSurCatalog').prop('disabled', !canAdd);

        if (!state.selProd || !state.data) {
            $('#pfCountCatalog').text('0');
            $el.html('<div class="pf-empty-hint">Vyberte produkt vlevo</div>');
            return;
        }

        if (p.ukonceny) {
            $('#pfCountCatalog').text('0');
            $el.html('<div class="pf-empty-hint">Produkt je ukončený</div>');
            return;
        }

        var linked = linkedSurIds(p.id);
        var catalog = (state.data.suroviny_catalog || []).filter(function(c) {
            if (linked[c.id]) return false;
            return matchText(c.nazev, state.filterSurCatalog) || matchText(c.nazev_en, state.filterSurCatalog);
        });

        var withReq = 0;
        catalog.forEach(function(c) {
            if (catalogCandidates(c.id, p.id).length) withReq++;
        });
        $('#pfCountCatalog').text(catalog.length + (withReq ? ' (' + withReq + ' s požadavkem)' : ''));

        if (!catalog.length) {
            $el.html('<div class="pf-empty-hint">' + (state.filterSurCatalog ? 'Nic nevyhovuje filtru' : 'Všechny suroviny už jsou přiřazené') + '</div>');
            return;
        }

        catalog.forEach(function(c) {
            var en = c.nazev_en ? '<div class="pf-card-meta">' + esc(c.nazev_en) + '</div>' : '';
            var candidates = catalogCandidates(c.id, p.id);
            var reqHtml = '';
            if (candidates.length) {
                reqHtml = '<div class="pf-catalog-reqs">';
                candidates.forEach(function(req) {
                    reqHtml += renderCatalogReqRow(req, c.id, true);
                });
                reqHtml += '</div>';
            } else {
                reqHtml = '<div class="pf-catalog-no-req text-muted">žádný otevřený požadavek k připojení</div>';
            }
            var cardCls = 'pf-card pf-card-sur pf-card-sur-catalog';
            if (candidates.length === 1) cardCls += ' pf-catalog-single-req';
            var $card = $('<div class="' + cardCls + '" data-sid="' + c.id + '">' +
                '<span class="pf-catalog-plus"><i class="glyphicon glyphicon-plus"></i></span>' +
                '<div class="pf-card-title">' + esc(c.nazev) + '</div>' + en + reqHtml + '</div>');
            $el.append($card);
        });
    }

    function updateLayout() {
        var $cols = $('#pfColumns');
        $cols.removeClass('pf-focus-zak pf-focus-product pf-has-product');
        if (state.selProd) {
            $cols.addClass('pf-focus-product pf-has-product');
        } else if (state.selZak !== null) {
            $cols.addClass('pf-focus-zak');
        }
    }

    function renderAll() {
        renderZakaznici();
        renderProdukty();
        renderProductBar();
        renderAssigned();
        renderCatalog();
        renderFilterChips();
        updateLayout();
    }

    function toggleZak(id) {
        state.selZak = (state.selZak === id) ? null : id;
        renderAll();
    }

    function toggleProd(id) {
        state.selProd = (state.selProd === id) ? null : id;
        state.armedUnlinkId = null;
        state.productExtras = null;
        if (state.selProd) loadProductExtras(state.selProd);
        renderAll();
    }

    function clearSelection() {
        state.selZak = null;
        state.selProd = null;
        renderAll();
    }

    function openZakModal(id, nazev) {
        $('#pfZakId').val(id || 0);
        $('#pfZakNazev').val(nazev || '');
        $('#pfModalZakTitle').text(id ? 'Upravit zákazníka' : 'Nový zákazník');
        $('#pfModalZak').modal('show');
    }

    function fillZakSelect() {
        var $s = $('#pfProdZakaznik').empty();
        if (!state.data) return;
        state.data.zakaznici.forEach(function(z) {
            $s.append('<option value="' + z.id + '">' + esc(z.nazev) + '</option>');
        });
        if (state.selZak > 0) $s.val(state.selZak);
    }

    function openProdModal(id) {
        fillZakSelect();
        if (id) {
            var p = getProduct(id);
            if (!p) return;
            $('#pfProdId').val(p.id);
            $('#pfProdNazev').val(p.nazev);
            $('#pfProdZakaznik').val(p.id_zakaznik || '');
            $('#pfProdPriorita').val(p.priorita ? '1' : '0');
            $('#pfModalProdTitle').text('Upravit produkt');
        } else {
            $('#pfProdId').val(0);
            $('#pfProdNazev').val('');
            $('#pfProdPriorita').val('0');
            $('#pfModalProdTitle').text('Nový produkt');
        }
        $('#pfModalProd').modal('show');
    }

    function reloadKeepingSelection(cb) {
        var keepZ = state.selZak, keepP = state.selProd;
        loadTree(function() {
            state.selZak = keepZ;
            state.selProd = keepP;
            state.armedUnlinkId = null;
            state.armedEndProdId = null;
            state.armedRestoreProdId = null;
            if (keepP) loadProductExtras(keepP);
            renderAll();
            if (cb) cb();
        });
    }

    $(document).ready(function() {
        if (!$('#portfolio-dashboard').length) return;

        $('#main-content').addClass('pf-main-content');
        loadTree(function() {
            var params = new URLSearchParams(window.location.search);
            var prodId = parseInt(params.get('pf_prod'), 10);
            if (prodId > 0 && state.data) {
                var p = getProduct(prodId);
                if (p) {
                    if (p.id_zakaznik) state.selZak = p.id_zakaznik;
                    state.selProd = prodId;
                    loadProductExtras(prodId);
                    renderAll();
                }
            }
        });

        $('#pfFilterZak').on('input', function() {
            state.filterZak = $(this).val().toLowerCase().trim();
            renderZakaznici();
        });
        $('#pfFilterProd').on('input', function() {
            state.filterProd = $(this).val().toLowerCase().trim();
            renderProdukty();
        });
        $('#pfFilterSurAssigned').on('input', function() {
            state.filterSurAssigned = $(this).val().toLowerCase().trim();
            renderAssigned();
        });
        $('#pfFilterSurCatalog').on('input', function() {
            state.filterSurCatalog = $(this).val().toLowerCase().trim();
            renderCatalog();
        });

        $('#pfFilterUrgent').on('click', function() {
            state.filterUrgent = !state.filterUrgent;
            $(this).toggleClass('active', state.filterUrgent);
            renderProdukty();
        });
        $('#pfFilterEnded').on('click', function() {
            state.filterEnded = !state.filterEnded;
            $(this).toggleClass('active', state.filterEnded);
            renderZakaznici();
            renderProdukty();
        });

        $(document).on('click', '.pf-chip-clear', function(e) {
            e.preventDefault();
            if ($(this).data('clear') === 'zak') state.selZak = null;
            if ($(this).data('clear') === 'prod') state.selProd = null;
            renderAll();
        });

        $(document).on('click', '.pf-card-zak', function(e) {
            if ($(e.target).closest('.pf-card-edit').length) return;
            toggleZak(parseInt($(this).data('id'), 10));
        });
        $(document).on('click', '.pf-card-edit', function(e) {
            e.stopPropagation();
            var id = parseInt($(this).closest('.pf-card-zak').data('id'), 10);
            var z = state.data.zakaznici.find(function(x) { return x.id === id; });
            openZakModal(id, z ? z.nazev : '');
        });
        $(document).on('click', '.pf-sur-req-link', function(e) {
            e.stopPropagation();
        });

        $(document).on('click', '.pf-card-prod', function(e) {
            if ($(e.target).closest('.pf-prio-toggle, .pf-prod-end, .pf-prod-restore').length) return;
            toggleProd(parseInt($(this).data('id'), 10));
        });
        $(document).on('click', '.pf-prio-toggle', function(e) {
            e.stopPropagation();
            if (!state.data || !state.data.migration_ok) return;
            var id = parseInt($(this).closest('.pf-card-prod').data('id'), 10);
            $.post('includes/ajax_portfolio.php', { action: 'toggle_priorita', id: id }, function(r) {
                if (typeof r === 'string') r = JSON.parse(r);
                if (r.error) { alert(r.error); return; }
                reloadKeepingSelection();
            }, 'json');
        });

        $(document).on('click', '.pf-btn-edit-prod', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openProdModal(state.selProd);
        });

        $(document).on('click', '.pf-prod-end', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            var prodId = parseInt($(this).data('id'), 10);
            if (!prodId) return;
            if (state.armedEndProdId === prodId) {
                doEndProduct(prodId);
            } else {
                state.armedEndProdId = prodId;
                state.armedRestoreProdId = null;
                state.armedUnlinkId = null;
                $('.pf-prod-end').removeClass('pf-end-armed');
                $('.pf-prod-restore').removeClass('pf-restore-armed');
                $('.pf-sur-remove').removeClass('pf-remove-armed');
                $(this).addClass('pf-end-armed').attr('title', 'Klikněte znovu pro ukončení');
            }
        });

        $(document).on('click', '.pf-prod-restore', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            var prodId = parseInt($(this).data('id'), 10);
            if (!prodId) return;
            if (state.armedRestoreProdId === prodId) {
                doRestoreProduct(prodId);
            } else {
                state.armedRestoreProdId = prodId;
                state.armedEndProdId = null;
                state.armedUnlinkId = null;
                $('.pf-prod-restore').removeClass('pf-restore-armed');
                $('.pf-prod-end').removeClass('pf-end-armed');
                $('.pf-sur-remove').removeClass('pf-remove-armed');
                $(this).addClass('pf-restore-armed').attr('title', 'Klikněte znovu pro obnovení');
            }
        });

        $(document).on('click', '.pf-card-sur-catalog', function(e) {
            if ($(e.target).closest('.pf-catalog-req-click').length) return;
            var sid = parseInt($(this).data('sid'), 10);
            var $card = $(this);
            var candidates = catalogCandidates(sid, state.selProd);
            if (candidates.length > 1) {
                return;
            }
            if (candidates.length === 1) {
                doLinkPozadavek(candidates[0].req_id, $card);
                return;
            }
            if (!confirm('K této surovině není otevřený požadavek. Přidat jen do receptury produktu?')) {
                return;
            }
            $card.addClass('pf-card-adding');
            $.post('includes/ajax_portfolio.php', {
                action: 'link_surovina',
                id_produkt: state.selProd,
                id_surovina: sid
            }, function(r) {
                if (typeof r === 'string') r = JSON.parse(r);
                if (r.error) { alert(r.error); $card.removeClass('pf-card-adding'); return; }
                reloadKeepingSelection();
            }, 'json');
        });

        $(document).on('click', '.pf-catalog-req-click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var reqId = parseInt($(this).data('req'), 10);
            doLinkPozadavek(reqId, $(this).closest('.pf-card-sur-catalog'));
        });

        $(document).on('click', '.pf-link-candidate', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var reqId = parseInt($(this).data('req'), 10);
            doLinkPozadavek(reqId, $(this).closest('.pf-card-sur-assigned'));
        });

        $(document).on('click', '.pf-sur-remove', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            var linkId = parseInt($(this).attr('data-link'), 10);
            if (!linkId) return;
            if (state.armedUnlinkId === linkId) {
                doUnlink(linkId);
            } else {
                state.armedUnlinkId = linkId;
                state.armedEndProdId = null;
                state.armedRestoreProdId = null;
                $('.pf-prod-end').removeClass('pf-end-armed');
                $('.pf-prod-restore').removeClass('pf-restore-armed');
                $('.pf-sur-remove').removeClass('pf-remove-armed');
                $(this).addClass('pf-remove-armed').attr('title', 'Klikněte znovu pro odebrání');
            }
        });

        $('#portfolio-dashboard').on('click', function(e) {
            if ($(e.target).closest('.pf-sur-remove, .pf-prod-end, .pf-prod-restore').length) return;
            if (state.armedUnlinkId) {
                state.armedUnlinkId = null;
                $('.pf-sur-remove').removeClass('pf-remove-armed');
            }
            if (state.armedEndProdId) {
                state.armedEndProdId = null;
                $('.pf-prod-end').removeClass('pf-end-armed');
            }
            if (state.armedRestoreProdId) {
                state.armedRestoreProdId = null;
                $('.pf-prod-restore').removeClass('pf-restore-armed');
            }
        });

        $('#pfBtnNewZak').on('click', function(e) { e.stopPropagation(); openZakModal(0, ''); });
        $('#pfBtnNewProd').on('click', function(e) {
            e.stopPropagation();
            if (!state.data.migration_ok) {
                alert('Spusťte migraci migrate_portfolio_produkty.sql');
                return;
            }
            openProdModal(0);
        });

        $('#pfZakSave').on('click', function() {
            $.post('includes/ajax_portfolio.php', {
                action: 'save_zakaznik', id: $('#pfZakId').val(), nazev: $('#pfZakNazev').val()
            }, function(r) {
                if (typeof r === 'string') r = JSON.parse(r);
                if (r.error) { alert(r.error); return; }
                $('#pfModalZak').modal('hide');
                var newId = r.id;
                loadTree(function() { state.selZak = newId; renderAll(); });
            }, 'json');
        });

        $('#pfProdSave').on('click', function() {
            $.post('includes/ajax_portfolio.php', {
                action: 'save_produkt',
                id: $('#pfProdId').val(),
                id_zakaznik: $('#pfProdZakaznik').val(),
                nazev: $('#pfProdNazev').val(),
                priorita: $('#pfProdPriorita').val()
            }, function(r) {
                if (typeof r === 'string') r = JSON.parse(r);
                if (r.error) { alert(r.error); return; }
                $('#pfModalProd').modal('hide');
                var newId = r.id, zid = parseInt($('#pfProdZakaznik').val(), 10);
                loadTree(function() {
                    state.selZak = zid;
                    state.selProd = newId;
                    renderAll();
                });
            }, 'json');
        });

        $('#pfBtnSirotcinec').on('click', function() {
            setSirotcinecView(!state.viewSirotcinec);
        });
        $('#pfBtnSirotClose').on('click', function() { setSirotcinecView(false); });
        $('#pfFilterSirot').on('input', function() {
            state.filterSirot = $(this).val().trim();
            loadSirotcinec();
        });

        $(document).on('click', '.pf-link-orphan', function(e) {
            e.stopPropagation();
            var reqId = parseInt($(this).data('req'), 10);
            if (!state.selProd) { alert('Nejdříve vyberte produkt v portfolio.'); return; }
            doLinkPozadavek(reqId, null, function() {
                if (state.viewSirotcinec) loadSirotcinec();
            });
        });

        $('#pfPoznamkaSave').on('click', function() {
            if (!state.selProd) return;
            $.post('includes/ajax_portfolio.php', {
                action: 'save_poznamka', id_produkt: state.selProd, poznamka: $('#pfPoznamkaText').val()
            }, function(r) {
                if (typeof r === 'string') r = JSON.parse(r);
                if (r.error) { alert(r.error); return; }
                var p = getProduct(state.selProd);
                if (p) p.poznamka = $('#pfPoznamkaText').val();
            }, 'json');
        });

        $('#pfChatSend').on('click', function() { sendChat(); });
        $('#pfChatInput').on('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); sendChat(); }
        });

        function sendChat() {
            if (!state.selProd) return;
            var txt = $('#pfChatInput').val().trim();
            if (!txt) return;
            $.post('includes/ajax_portfolio.php', {
                action: 'add_komentar', id_produkt: state.selProd, text: txt
            }, function(r) {
                if (typeof r === 'string') r = JSON.parse(r);
                if (r.error) { alert(r.error); return; }
                $('#pfChatInput').val('');
                loadProductExtras(state.selProd);
            }, 'json');
        }

        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                if (state.viewSirotcinec) setSirotcinecView(false);
                else if (state.armedUnlinkId) { state.armedUnlinkId = null; $('.pf-sur-remove').removeClass('pf-remove-armed'); }
                else clearSelection();
            }
        });
    });
})(jQuery);
