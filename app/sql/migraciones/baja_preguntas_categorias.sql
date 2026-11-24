UPDATE preguntas p
    INNER JOIN categorias c ON c.id = p.categoria_id
    SET p.habilitada = 0
WHERE c.habilitada = 0 AND p.habilitada = 1;