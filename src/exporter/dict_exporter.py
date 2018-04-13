"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
from settings import LINE_LENGTH_ON_PPRINT


class DictExporter:

    @staticmethod
    def generate_data(site):
        """Generate data for tests"""
        data = {}

        # properties
        data['properties'] = {
            'base_path': site.base_path,
            'name': site.name,
            'server_name': site.server_name,
            'export_files': site.export_files,
            'languages': site.languages,
            # ignored xml_path -> replaced with export_files
            'title': site.title,
            'acronym': site.acronym,
            'theme': site.theme,
            'css_url': site.css_url,
            'breadcrumb_title': site.breadcrumb_title,
            'breadcrumb_url': site.breadcrumb_url,
            'homepage__pid': site.homepage.pid,
            'files__len': len(site.files),
            'pages__ids': set(site.pages_by_pid.keys()),
            'pages__uuids': set(site.pages_by_uuid.keys()),
            'pages_content__paths': set(site.pages_content_by_path.keys()),
            'report': {
                'num_files': site.num_files,
                'num_pages': site.num_pages,
                'internal_links': site.internal_links,
                'absolute_links': site.absolute_links,
                'external_links': site.external_links,
                'file_links': site.file_links,
                'data_links': site.data_links,
                'mailto_links': site.mailto_links,
                'anchor_links': site.anchor_links,
                'broken_links': site.broken_links,
                'unknown_links': site.unknown_links,
            }
        }

        # site footer
        footers = {}
        data['properties']['footers'] = footers

        for language, links in site.footer.items():
            footers[language] = set([str(link) for link in links])

        # pages properties (language independant)
        pages_by_pid = {}
        data['pages_by_pid'] = pages_by_pid

        for pid, page in site.pages_by_pid.items():
            page_properties = {
                'pid': page.pid,
                'uuid': page.uuid,
                'template': page.template,
                'level': page.level,
                'children__len': len(page.children),
                'contents__keys': set(page.contents.keys()),
            }
            pages_by_pid[pid] = page_properties

            # page_contents (translations)
            contents = {}
            page_properties['contents'] = contents

            for language, page_content in page.contents.items():
                content_properties = {
                    'language': page_content.language,
                    'path': page_content.path,
                    'title': page_content.title,
                    'last_update': page_content.last_update,
                }
                contents[language] = content_properties

                # boxes for this page_content
                main_boxes = []
                content_properties['boxes'] = main_boxes

                for box in page_content.boxes:
                    main_boxes.append({
                        'title': box.title,
                        'type': box.type,
                        'content__start': box.content[:LINE_LENGTH_ON_PPRINT],
                    })

                # sidebar for this page_content
                sidebar_boxes = []
                content_properties['sidebar__boxes'] = sidebar_boxes

                for box in page_content.sidebar.boxes:
                    sidebar_boxes.append({
                        'title': box.title,
                        'type': box.type,
                        'content__start': box.content[:LINE_LENGTH_ON_PPRINT],
                    })

        return data
