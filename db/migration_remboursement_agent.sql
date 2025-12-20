-- =====================================================
-- SCRIPT DE MIGRATION - Module Remboursement Agent
-- À exécuter sur la base PostgreSQL existante
-- =====================================================

-- 1. Insérer les états du workflow (si pas déjà fait)
INSERT INTO status_demande (stat_libelle) VALUES 
  ('SOUMIS'),
  ('VALIDE_RRH'),
  ('VALIDE_DAAF'),
  ('ENGAGE'),
  ('PAYE'),
  ('REJETE')
ON CONFLICT DO NOTHING;
