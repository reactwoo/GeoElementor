import React, { useMemo, useState } from "react";
import apiFetch from "@wordpress/api-fetch";
import Modal from "./Modal";
import CountryPicker from "./CountryPicker";

const TYPES = ["Page", "Popup", "Section", "Form"];

export default function CreateRuleModal({ onClose, onCreated, isPro }) {
    const [title, setTitle] = useState("");
    const [type, setType] = useState("Page");
    const [countries, setCountries] = useState([]);
    const [busy, setBusy] = useState(false);

    const disabledTypes = useMemo(() => {
        if (isPro) return [];
        // Free: Page, Popup ; Pro adds Section, Form
        return ["Section", "Form"];
    }, [isPro]);

    const create = async () => {
        if (!title.trim() || countries.length === 0) {
            alert("Please enter a title and select at least one country.");
            return;
        }
        setBusy(true);
        try {
            const res = await apiFetch({
                path: "/geo-elementor/v1/rules",
                method: "POST",
                data: { title: title.trim(), type, countries }
            });
            onCreated?.(res);
            onClose?.();
        } catch (e) {
            // eslint-disable-next-line no-console
            console.error("Create rule failed", e);
            alert("Failed to create rule.");
        } finally {
            setBusy(false);
        }
    };

    return (
        <Modal
            title="Create Geo Rule"
            onClose={onClose}
            footer={
                <>
                    <button className="geo-el-primary" onClick={create} disabled={busy}>{busy ? "Creating…" : "Create"}</button>
                    <button onClick={onClose} style={{ padding: "8px 12px" }}>Cancel</button>
                </>
            }
        >
            <div style={{ display: "grid", gap: 12 }}>
                <div>
                    <label style={{ display: "block", fontWeight: 600, marginBottom: 6 }}>Title</label>
                    <input className="geo-el-input" value={title} onChange={e => setTitle(e.target.value)} placeholder="e.g. UK Promo Popup" />
                </div>
                <div>
                    <label style={{ display: "block", fontWeight: 600, marginBottom: 6 }}>Type</label>
                    <select className="geo-el-select" value={type} onChange={e => setType(e.target.value)}>
                        {TYPES.map(t => (
                            <option key={t} value={t} disabled={disabledTypes.includes(t)}>
                                {t}{disabledTypes.includes(t) ? " (Pro)" : ""}
                            </option>
                        ))}
                    </select>
                    {!isPro && <div className="geo-el-help">Sections & Forms require Pro.</div>}
                </div>
                <CountryPicker value={countries} onChange={setCountries} />
            </div>
        </Modal>
    );
}
