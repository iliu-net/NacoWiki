doc:
	./scripts/docgen
	./scripts/docrun

serve:
	( cd docs && php -S localhost:9000 )
