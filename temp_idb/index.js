const { indexedDB } = require('fake-indexeddb');
const fs = require('fs');

async function test() {
    let content = fs.readFileSync('../p3_dump.json', 'utf16le');
    if (content.charCodeAt(0) === 0xFEFF) {
        content = content.slice(1);
    }
    const p3 = JSON.parse(content);
    
    const db = await new Promise((resolve, reject) => {
        const req = indexedDB.open('TestDB', 1);
        req.onupgradeneeded = e => {
            const db = e.target.result;
            const store = db.createObjectStore('sponsorships', { keyPath: 'id' });
            store.createIndex('sponsor_id', 'sponsor_id', { unique: false });
            store.createIndex('sponsorship_status_id', 'sponsorship_status_id', { unique: false });
            store.createIndex('identity_number', 'identity_number', { unique: false });
            store.createIndex('orphan_name', 'orphan_name', { unique: false });
        };
        req.onsuccess = e => resolve(e.target.result);
        req.onerror = e => reject(e.target.error);
    });
    
    await new Promise((resolve, reject) => {
        const tx = db.transaction('sponsorships', 'readwrite');
        const store = tx.objectStore('sponsorships');
        for (const item of p3) {
            if (item.sponsor_id === null || item.sponsor_id === undefined) item.sponsor_id = 0;
            if (item.sponsorship_status_id === null || item.sponsorship_status_id === undefined) item.sponsorship_status_id = 0;
            const req = store.put(item);
            req.onerror = e => { console.error("PUT ERROR:", e.target.error, "for id", item.id); };
        }
        tx.oncomplete = () => resolve();
        tx.onerror = e => { console.error("TX ERROR:", tx.error); reject(tx.error); };
    });
    console.log("SUCCESS! All records inserted without DataError.");
}

test().catch(e => console.error("FATAL:", e));
