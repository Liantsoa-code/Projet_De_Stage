-- ============================================================
-- Insertion des nouvelles aires protégées Fanamby
-- Coordonnées WGS84 (lat/lng) converties depuis Laborde Madagascar
-- ============================================================

-- Site : Andrafiamena - Andavakoera
INSERT INTO sites (nom, description, polygone_coordonnees, created_at, updated_at) VALUES (
  'Andrafiamena - Andavakoera',
  'Aire protégée de forêt sèche de montagne dans le Nord de Madagascar. Gérée par Fanamby.',
  '[{"lat": -12.8072, "lng": 49.1978}, {"lat": -12.8236, "lng": 49.327}, {"lat": -12.9131, "lng": 49.4019}, {"lat": -13.0038, "lng": 49.3847}, {"lat": -13.0945, "lng": 49.3583}, {"lat": -13.2039, "lng": 49.2952}, {"lat": -13.2231, "lng": 49.2031}, {"lat": -13.1697, "lng": 49.1378}, {"lat": -13.0794, "lng": 49.1275}, {"lat": -12.9617, "lng": 49.1352}, {"lat": -12.8072, "lng": 49.1978}]',
  NOW(),
  NOW()
);

-- Site : Loky - Manambato
INSERT INTO sites (nom, description, polygone_coordonnees, created_at, updated_at) VALUES (
  'Loky - Manambato',
  'Aire protégée incluant des écosystèmes de forêt humide sempervirente dans le Nord-Est de Madagascar.',
  '[{"lat": -12.9098, "lng": 49.6416}, {"lat": -12.9261, "lng": 49.7709}, {"lat": -13.0147, "lng": 49.8921}, {"lat": -13.1325, "lng": 49.8755}, {"lat": -13.1973, "lng": 49.775}, {"lat": -13.2534, "lng": 49.6466}, {"lat": -13.2277, "lng": 49.5447}, {"lat": -13.1378, "lng": 49.5065}, {"lat": -13.0201, "lng": 49.5141}, {"lat": -12.9098, "lng": 49.6416}]',
  NOW(),
  NOW()
);

-- Site : Anjozorobe - Angavo
INSERT INTO sites (nom, description, polygone_coordonnees, created_at, updated_at) VALUES (
  'Anjozorobe - Angavo',
  'Corridor forestier de l''est du Plateau Central malgache. Importante zone de captage d''eau pour Antananarivo.',
  '[{"lat": -18.2811, "lng": 47.8941}, {"lat": -18.2981, "lng": 48.0267}, {"lat": -18.3701, "lng": 48.0653}, {"lat": -18.4878, "lng": 48.0474}, {"lat": -18.6054, "lng": 48.0296}, {"lat": -18.7322, "lng": 47.9928}, {"lat": -18.787, "lng": 47.9174}, {"lat": -18.7604, "lng": 47.8507}, {"lat": -18.6431, "lng": 47.8308}, {"lat": -18.4894, "lng": 47.8391}, {"lat": -18.3718, "lng": 47.8476}, {"lat": -18.2811, "lng": 47.8941}]',
  NOW(),
  NOW()
);

-- Site : Menabe Antimena
INSERT INTO sites (nom, description, polygone_coordonnees, created_at, updated_at) VALUES (
  'Menabe Antimena',
  'Forêt sèche d''importance mondiale sur la côte ouest de Madagascar, incluant des tsingy et mangroves.',
  '[{"lat": -19.6308, "lng": 44.3864}, {"lat": -19.6143, "lng": 44.5296}, {"lat": -19.6702, "lng": 44.7007}, {"lat": -19.7434, "lng": 44.7953}, {"lat": -19.8426, "lng": 44.7752}, {"lat": -19.9776, "lng": 44.7164}, {"lat": -20.0857, "lng": 44.677}, {"lat": -20.1845, "lng": 44.6185}, {"lat": -20.2286, "lng": 44.5222}, {"lat": -20.2005, "lng": 44.4268}, {"lat": -20.1093, "lng": 44.3611}, {"lat": -19.9735, "lng": 44.3342}, {"lat": -19.8111, "lng": 44.3554}, {"lat": -19.6308, "lng": 44.3864}]',
  NOW(),
  NOW()
);

-- Site : Allée des Baobabs
INSERT INTO sites (nom, description, polygone_coordonnees, created_at, updated_at) VALUES (
  'Allée des Baobabs',
  'Site naturel emblématique de Madagascar, classé monument naturel. Alignement de Baobabs (Adansonia grandidieri) près de Morondava.',
  '[{"lat": -20.232, "lng": 44.4197}, {"lat": -20.2303, "lng": 44.4265}, {"lat": -20.2331, "lng": 44.4341}, {"lat": -20.2385, "lng": 44.4359}, {"lat": -20.2457, "lng": 44.4358}, {"lat": -20.2529, "lng": 44.4329}, {"lat": -20.2565, "lng": 44.428}, {"lat": -20.2555, "lng": 44.4214}, {"lat": -20.2518, "lng": 44.4166}, {"lat": -20.2437, "lng": 44.4158}, {"lat": -20.2365, "lng": 44.4168}, {"lat": -20.232, "lng": 44.4197}]',
  NOW(),
  NOW()
);

-- Vide la liste des sites mise en cache pour la page d'accueil (Site::pourAccueil),
-- sinon les nouveaux sites n'y apparaissent qu'au bout d'une journée.
DELETE FROM cache WHERE `key` LIKE '%sites.accueil';
