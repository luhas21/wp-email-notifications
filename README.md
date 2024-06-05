# wp-email-notifications

Vysvětlení

1. Funkce create_subscriber_table: Tato funkce vytváří tabulku v databázi pro uložení emailů a jejich stavů potvrzení.
2. Registrace a přidání stránek pro nastavení: • email_notifications_settings a email_notifications_settings_page registrují nastavení pluginu a přidávají stránku pro výběr typů příspěvků, pro které se budou notifikace rozesílat.
3. Odesílání notifikací: • send_notification_on_publish odesílá emaily, když je publikován nový příspěvek nebo stránka. • add_cpt_notifications přidává akce pro vlastní typy příspěvků (CPT).
4. Odhlášení odběratelů: • handle_unsubscribe_request zpracovává odhlášení odběratelů.
5. Formulář pro přidání emailu: • subscriber_form zobrazí formulář pro přidání emailu a odesílá potvrzovací email.
6. Potvrzení emailu: • handle_email_confirmation zpracovává potvrzení emailů.
7. Meta box pro zakázání notifikací: • add_notification_metabox, notification_metabox_callback a save_notification_meta přidávají meta box na stránku úprav příspěvků pro zakázání notifikací pro konkrétní příspěvky.
8. Import a export emailů: • email_management_page přidává stránku v administraci pro import a export emailů. • export_emails exportuje emaily do CSV. • import_emails importuje emaily z CSV.
