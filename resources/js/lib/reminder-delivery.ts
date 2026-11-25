export const deliveryLabels = {
    pending: 'Tesztlevél sorban áll',
    sending: 'Tesztküldés folyamatban',
    accepted: 'A Mailpit átvette a tesztlevelet',
    blocked: 'Tesztküldés letiltva',
    unknown: 'A tesztlevél átvétele bizonytalan',
};

export const deliveryReasons: Record<string, string> = {
    pilot_restored:
        'A pilot mentésének visszaállításakor a várakozó küldés leállt.',
    organization_closing: 'A cég írásvédett állapotban van.',
    organization_paused: 'A cég emlékeztetői szünetelnek.',
    balance_unverified: 'A számla fennálló összege új ellenőrzést igényel.',
    balance_rechecked: 'A számla egyenlegét újra ellenőrizték.',
    invoice_deadline_changed: 'A számla fizetési határideje megváltozott.',
    invoice_disputed: 'A számla vitatott.',
    customer_do_not_contact: 'Az ügyfél emlékeztetői tiltva vannak.',
    billing_contact_changed: 'Az ügyfél pénzügyi e-mail-címe megváltozott.',
    billing_confirmation_revoked:
        'A pénzügyi e-mail-cím megerősítését visszavonták.',
    cooldown:
        'A legutóbbi küldési kísérlettől számítva hét napnak kell eltelnie.',
    outside_window: 'A jóváhagyás érvényessége alatt nincs küldési ablak.',
    calendar_unavailable: 'Ehhez az évhez még nincs ellenőrzött munkanaptár.',
    missing_record: 'A számla vagy a jóváhagyás már nem érhető el.',
    delivery_cancelled: 'A tulajdonos leállította a várakozó tesztküldést.',
    approval_revoked: 'A tulajdonos visszavonta a jóváhagyást.',
    approval_expired:
        'A jóváhagyás lejárt, mielőtt a feldolgozó elindult volna.',
    approval_changed: 'A levéltervezet vagy a jóváhagyás közben megváltozott.',
    test_address_required: 'A tesztküldéshez .test végződésű cím szükséges.',
    transport_unconfirmed: 'Ellenőrizd a Mailpit beérkező leveleit.',
};

export type DeliveryStatus = keyof typeof deliveryLabels;
