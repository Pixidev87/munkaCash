export const deliveryLabels = {
    pending: 'Tesztlevél sorban áll',
    sending: 'Tesztküldés folyamatban',
    accepted: 'A Mailpit átvette a tesztlevelet',
    blocked: 'Tesztküldés letiltva',
    unknown: 'A tesztlevél átvétele bizonytalan',
};

export const deliveryReasons: Record<string, string> = {
    organization_paused: 'A cég emlékeztetői szünetelnek.',
    customer_do_not_contact: 'Az ügyfél emlékeztetői tiltva vannak.',
    cooldown:
        'A legutóbbi küldési kísérlettől számítva hét napnak kell eltelnie.',
    outside_window: 'A jóváhagyás érvényessége alatt nincs küldési ablak.',
    calendar_unavailable: 'Ehhez az évhez még nincs ellenőrzött munkanaptár.',
    missing_record: 'A számla vagy a jóváhagyás már nem érhető el.',
    approval_expired:
        'A jóváhagyás lejárt, mielőtt a feldolgozó elindult volna.',
    approval_changed: 'A levéltervezet vagy a jóváhagyás közben megváltozott.',
    test_address_required: 'A tesztküldéshez .test végződésű cím szükséges.',
    transport_unconfirmed: 'Ellenőrizd a Mailpit beérkező leveleit.',
};

export type DeliveryStatus = keyof typeof deliveryLabels;
