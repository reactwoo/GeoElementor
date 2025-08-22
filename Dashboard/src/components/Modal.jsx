import React, { useEffect } from "react";

export default function Modal({ title, children, footer, onClose }) {
    useEffect(() => {
        const onKey = (e) => { if (e.key === "Escape") onClose?.(); };
        document.addEventListener("keydown", onKey);
        return () => document.removeEventListener("keydown", onKey);
    }, [onClose]);

    return (
        <div className="geo-el-modal-backdrop" role="dialog" aria-modal="true">
            <div className="geo-el-modal">
                <div className="geo-el-modal__header" style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                    <strong>{title}</strong>
                    <button onClick={onClose} aria-label="Close" style={{ border: "none", background: "none", cursor: "pointer", fontSize: 18, lineHeight: 1 }}>×</button>
                </div>
                <div className="geo-el-modal__body">
                    {children}
                </div>
                <div className="geo-el-modal__footer">
                    {footer}
                </div>
            </div>
        </div>
    );
}
