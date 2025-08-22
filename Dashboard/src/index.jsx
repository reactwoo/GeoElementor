import React, { useEffect, useState } from "react";
import { createRoot } from "@wordpress/element";
import apiFetch from "@wordpress/api-fetch";
import "./index.scss";

import TopLocationsChart from "./components/TopLocationsChart";
import RulesUsageDonut from "./components/RulesUsageDonut";
import EngagementByCountry from "./components/EngagementByCountry";
import ItemsToolbar from "./components/ItemsToolbar";
import ItemsTable from "./components/ItemsTable";
import CreateRuleModal from "./components/CreateRuleModal";

function App() {
    const [loading, setLoading] = useState(true);
    const [stats, setStats] = useState(null);
    const [filters, setFilters] = useState({ type: "All", country: "All", q: "" });
    const [showCreate, setShowCreate] = useState(false);
    const [isPro, setIsPro] = useState(false);

    async function load() {
        setLoading(true);
        try {
            const res = await apiFetch({ path: "/geo-elementor/v1/dashboard" });
            setStats(res);
        } catch (e) {
            // eslint-disable-next-line no-console
            console.error("Failed to load dashboard:", e);
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        if (window.GEO_EL?.nonce) {
            apiFetch.use(apiFetch.createNonceMiddleware(window.GEO_EL.nonce));
        }
        setIsPro(!!window.GEO_EL?.isPro);
        load();
    }, []);

    const onPatched = (updated) => {
        setStats((prev) => {
            if (!prev) return prev;
            const items = prev.items.map(i => (i.id === updated.id ? updated : i));
            return { ...prev, items };
        });
    };

    const onCreated = (item) => {
        setStats((prev) => {
            if (!prev) return prev;
            const items = [item, ...prev.items];
            // bump rules usage counters (rough, mock)
            const ru = prev.rulesUsage.map(r =>
                r.type === (item.type + (item.type.endsWith('s') ? '' : (item.type === 'Page' ? 's' : item.type === 'Popup' ? 's' : item.type === 'Section' ? 's' : item.type === 'Form' ? 's' : '')))
                    ? { ...r, count: r.count + 1 }
                    : r
            );
            return { ...prev, items, rulesUsage: ru };
        });
    };

    if (loading) {
        return <div className="geo-el-admin">Loading…</div>;
    }

    if (!stats) {
        return <div className="geo-el-admin">Unable to load dashboard data.</div>;
    }

    return (
        <div className="geo-el-admin">
            {/* Cards Row */}
            <div className="geo-el-cards">
                <TopLocationsChart data={stats.topLocations} />
                <RulesUsageDonut data={stats.rulesUsage} />
                <EngagementByCountry labels={stats.engagement.labels} byCountry={stats.engagement.byCountry} />
            </div>

            {/* Filters + Create */}
            <ItemsToolbar
                types={stats.filters.types}
                countries={stats.filters.countries}
                value={filters}
                onChange={setFilters}
                onCreate={() => setShowCreate(true)}
            />

            {/* Table with inline toggle */}
            <ItemsTable items={stats.items} filters={filters} onStatusPatched={onPatched} />

            {showCreate && (
                <CreateRuleModal
                    isPro={isPro}
                    onClose={() => setShowCreate(false)}
                    onCreated={onCreated}
                />
            )}
        </div>
    );
}

(function bootstrap() {
    const mount = document.getElementById("geo-el-admin-app");
    if (!mount) return;
    const root = createRoot(mount);
    root.render(<App />);
})();
