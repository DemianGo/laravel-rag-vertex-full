# STATUS PROJETO dom 28 set 2025 16:04:14 -03
## APIs Funcionais:
  GET|HEAD  api/bypass-documents ........................................................................ upload-bypass.list › BypassUploadController@list
  GET|HEAD  api/docs/list ......................................................................................................... RagController@listDocs
  GET|HEAD  api/health ................................................................................................................................... 
  GET|POST|HEAD api/rag/answer ................................................................................................ RagAnswerController@answer
  POST      api/rag/batch-ingest ............................................................................................... RagController@batchIngest
  POST      api/rag/cache/clear ................................................................................................. RagController@clearCache
  GET|HEAD  api/rag/cache/stats ................................................................................................. RagController@cacheStats
  GET|POST|HEAD api/rag/debug/echo .................................................................................................... RagController@echo
  GET|HEAD  api/rag/embeddings/stats ........................................................................................ RagController@embeddingStats
  POST      api/rag/generate-answer ......................................................................................... RagController@generateAnswer
  POST      api/rag/ingest .......................................................................................................... RagController@ingest
  POST      api/rag/ingest-quality ....................................................................................... RagController@ingestWithQuality
  GET|HEAD  api/rag/metrics ........................................................................................................ RagController@metrics
  GET|POST|HEAD api/rag/ping ............................................................................................................................. 
  GET|HEAD  api/rag/preview ........................................................................................................ RagController@preview
  GET|POST|HEAD api/rag/query ........................................................................................................ RagController@query
  POST      api/rag/reprocess-document ................................................................................... RagController@reprocessDocument
  GET|HEAD  api/vertex/generate .......................................................................................... VertexRagController@generateGet
  POST      api/vertex/generate ......................................................................................... VertexRagController@generatePost
